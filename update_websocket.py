import requests
import json
import mysql.connector
import websocket
import time
import threading

MARKETS_IDS = {}
LAST_CANDLESTICKS_15m = {}
LAST_CANDLESTICKS_1h = {}
LAST_CANDLESTICKS_4h = {}

def get_markets_id():
    db = mysql.connector.connect(
        host="localhost", user="root", password="", database="cfdsm")
    cursor = db.cursor()
    cursor.execute('SELECT id, name from markets')
    for market in cursor.fetchall():
        MARKETS_IDS.update({market[1]: market[0]})

def get_last_candlesticks():
    db = mysql.connector.connect(
        host="localhost", user="root", password="", database="cfdsm")
    cursor = db.cursor()
    cursor.execute("""SELECT market_id, MAX(open_time) from klines15ms GROUP BY market_id""")
    for kline in cursor.fetchall():
        LAST_CANDLESTICKS_15m.update({kline[0]: kline[1]})

    cursor.execute("""SELECT market_id, MAX(open_time) from klines1hs GROUP BY market_id""")
    for kline in cursor.fetchall():
        LAST_CANDLESTICKS_1h.update({kline[0]: kline[1]})

    cursor.execute("""SELECT market_id, MAX(open_time) from klines4hs GROUP BY market_id""")
    for kline in cursor.fetchall():
        LAST_CANDLESTICKS_4h.update({kline[0]: kline[1]})

def get_markets():
    url = "https://fapi.binance.com/fapi/v1/exchangeInfo"
    req = requests.get(url).text
    markets = (json.loads(req))["symbols"]
    sql = """INSERT INTO markets (name, start_time, created_at, updated_at) VALUES """
    for market in markets:
        sql += """('""" + market["symbol"] + """', FROM_UNIXTIME(""" + str(int(
            market["onboardDate"])/1000) + """), CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP()),"""
    sql = sql[:len(sql)-1]
    try:
        connection = mysql.connector.connect(
            host="localhost",
            user="root",
            password="",
            database="cfdsm"
        )

        with connection.cursor() as cursor:
            cursor.execute(sql)
            connection.commit()
    except mysql.connector.Error as e:
        print(e)

def get_params():
    markets = [] #Array de mercados

    params = {}
    paramsKlines15m = []
    paramsKlines1h = []
    paramsKlines4h = []

    # obtenemos los mercados
    db = mysql.connector.connect(
        host="localhost", user="root", password="", database="cfdsm")
    cursor = db.cursor()
    cursor.execute('SELECT name from markets')

    for market in cursor.fetchall():
        markets.append(str(market[0]).lower())



    for market in markets:
        paramsKlines15m.append(market + "@kline_15m")
        paramsKlines1h.append(market + "@kline_1h")
        paramsKlines4h.append(market + "@kline_4h")

    params.update({'klines_15m' : paramsKlines15m})
    params.update({'klines_1h' : paramsKlines1h})
    params.update({'klines_4h' : paramsKlines4h})
    params.update({'24h_ticker': ["!ticker@arr"]})

    return params

def get_socket(params, id):
    ws = websocket.create_connection("wss://fstream.binance.com/ws/")
    request = json.dumps({
        "method": "SUBSCRIBE",
        "params": params,
        "id": id
    })
    ws.send(request)
    return ws

def manage_data_ws(response):
    db = mysql.connector.connect(
        host="localhost", user="root", password="", database="cfdsm")
    cursor = db.cursor()
    data = []
    for respons in response:
        dataToLoad = json.loads(str(respons)[2:len(str(respons))-1])
        if(not ('result' in dataToLoad)):
            data.append(dataToLoad)

    for dat in data:
        if('e' in dat and dat['e'] == "kline"):
            table = "klines" + dat['k']['i'] + "s"
            if((dat['k']['i'] == "15m" and (not MARKETS_IDS[dat['s']] in LAST_CANDLESTICKS_15m or LAST_CANDLESTICKS_15m[MARKETS_IDS[dat['s']]] != dat['k']['t'])) or
                (dat['k']['i'] == "1h" and (not MARKETS_IDS[dat['s']] in LAST_CANDLESTICKS_1h or LAST_CANDLESTICKS_1h[MARKETS_IDS[dat['s']]] != dat['k']['t'])) or
                (dat['k']['i'] == "4h" and (not MARKETS_IDS[dat['s']] in LAST_CANDLESTICKS_4h or LAST_CANDLESTICKS_4h[MARKETS_IDS[dat['s']]] != dat['k']['t']))
            ):
                cursor.execute(
                    """INSERT INTO {} (market_id, open_time, close_time, open, close, high, low, base_volume, taker_volume,
                    created_at, updated_at) VALUES
                    ({}, FROM_UNIXTIME({}), FROM_UNIXTIME({}), {}, {}, {}, {}, {}, {}, CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP())""".format(
                        table, MARKETS_IDS[dat['k']['s']], dat['k']['t']/1000, dat['k']['T']/1000, dat['k'][
                            'o'], dat['k']['c'], dat['k']['h'], dat['k']['l'], dat['k']['v'], dat['k']['V']
                ))
                if(dat['k']['i'] == "15m"):
                    LAST_CANDLESTICKS_15m.update({MARKETS_IDS[dat['s']]: dat['k']['t']})
                if(dat['k']['i'] == "1h"):
                    LAST_CANDLESTICKS_1h.update({MARKETS_IDS[dat['s']]: dat['k']['t']})
                if(dat['k']['i'] == "4h"):
                    LAST_CANDLESTICKS_4h.update({MARKETS_IDS[dat['s']]: dat['k']['t']})
            else:
                cursor.execute("""UPDATE {} SET close={}, high={}, low={}, base_volume={}, taker_volume={}, updated_at=CURRENT_TIMESTAMP()
                    WHERE market_id = {} AND open_time = FROM_UNIXTIME({})""".format(
                        table, dat['k']['c'], dat['k']['h'], dat['k']['l'], dat['k']['v'], dat['k']['V'], MARKETS_IDS[dat['s']], dat['k']['t']/1000
                    )
                )

    db.commit()


get_markets_id()
if(len(MARKETS_IDS) == 0):
    get_markets()
    get_markets_id()

get_last_candlesticks()

socketParams = get_params()
i = 1
sockets = {}
used_id = {}
for key in socketParams:
    sockets.update({'ws_' + key: get_socket(socketParams[key], i)})
    used_id.update({'ws_' + key: i})
    i += 1

while True:
    response = []
    # Cargamos datos del servidor
    for key in sockets:
        try:
            response.append(sockets[key].recv_frame().data)
        except:
            sockets.update({key: get_socket(socketParams[key[3:]], used_id[key])})

    # Una vez tenemos una batería de datos, los volcamos en la base de datos
    thread = threading.Thread(target=manage_data_ws, args=(response,))
    thread.start()





