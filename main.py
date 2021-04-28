import requests
import json
import mysql.connector
from mysql.connector import connect, Error
import websocket
import time
from collections import Counter
import numpy as np

MARKETS_IDS = {}
LAST_CANDLESTICKS_15m = {}
LAST_CANDLESTICKS_1h = {}
LAST_CANDLESTICKS_4h = {}

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

def get_markets_id():
    db = mysql.connector.connect(
        host="localhost", user="root", password="", database="cfdsm")
    cursor = db.cursor()
    cursor.execute('SELECT id, name from markets')
    for market in cursor.fetchall():
        MARKETS_IDS.update({market[1]: market[0]})


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
        connection = connect(
            host="localhost",
            user="root",
            password="",
            database="cfdsm"
        )

        with connection.cursor() as cursor:
            cursor.execute(sql)
            connection.commit()
    except Error as e:
        print(e)


def get_params():
    # obtenemos los mercados
    db = mysql.connector.connect(
        host="localhost", user="root", password="", database="cfdsm")
    cursor = db.cursor()
    cursor.execute('SELECT name from markets')

    paramsKlines = []

    for name in cursor.fetchall():
        paramsKlines.append(str(name[0]).lower() + "@kline_15m")
        paramsKlines.append(str(name[0]).lower() + "@kline_1h")
        paramsKlines.append(str(name[0]).lower() + "@kline_4h")

    cursor.execute('SELECT name from markets')

    paramsTicker = []
    for name in cursor.fetchall():
        paramsTicker.append(str(name[0]).lower() + "@ticker")

    paramsKlinesSplit = []
    for i in range(ceil(len(paramsKlines)/50)):
        paramsKlinesSplit.append(paramsKlines[i*50:(i+1):50])

    paramsTickerSplit = []
    for i in range(ceil(len(paramsTicker)/50)):
        paramsTickerSplit.append(paramsTicker[i*50:(i+1):50])

    return {'klines': paramsKlinesSplit, 'ticker': paramsTickerSplit}


def insert_or_update_kline(klines):
    db = mysql.connector.connect(
        host="localhost", user="root", password="", database="cfdsm")
    cursor = db.cursor()

    for kline in klines:
        table = "klines" + kline['i'] + "s"
        if((kline['i'] == "15m" and (not MARKETS_IDS[kline['s']] in LAST_CANDLESTICKS_15m or LAST_CANDLESTICKS_15m[MARKETS_IDS[kline['s']]] != kline['t'])) or
            (kline['i'] == "1h" and (not MARKETS_IDS[kline['s']] in LAST_CANDLESTICKS_1h or LAST_CANDLESTICKS_1h[MARKETS_IDS[kline['s']]] != kline['t'])) or
            (kline['i'] == "4h" and (not MARKETS_IDS[kline['s']] in LAST_CANDLESTICKS_4h or LAST_CANDLESTICKS_4h[MARKETS_IDS[kline['s']]] != kline['t']))
        ):
            cursor.execute(
                """INSERT INTO {} (market_id, open_time, close_time, open, close, high, low, base_volume, taker_volume,
                created_at, updated_at) VALUES
                ({}, FROM_UNIXTIME({}), FROM_UNIXTIME({}), {}, {}, {}, {}, {}, {}, CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP())""".format(
                    table, MARKETS_IDS[kline['s']], kline['t']/1000, kline['T']/1000, kline[
                        'o'], kline['c'], kline['h'], kline['l'], kline['v'], kline['V']
            ))
            if(kline['i'] == "15m"):
                LAST_CANDLESTICKS_15m.update({MARKETS_IDS[kline['s']]: kline['t']})
            if(kline['i'] == "1h"):
                LAST_CANDLESTICKS_1h.update({MARKETS_IDS[kline['s']]: kline['t']})
            if(kline['i'] == "4h"):
                LAST_CANDLESTICKS_4h.update({MARKETS_IDS[kline['s']]: kline['t']})
        else:
            cursor.execute("""UPDATE {} SET close={}, high={}, low={}, base_volume={}, taker_volume={}, updated_at=CURRENT_TIMESTAMP()
                WHERE market_id = {} AND open_time = FROM_UNIXTIME({})""".format(
                    table, kline['c'], kline['h'], kline['l'], kline['v'], kline['V'], MARKETS_IDS[kline['s']], kline['t']/1000
                )
            )

    db.commit()


def on_message(ws, msg):
    print("\n\n new Message")
    print(msg)


def on_error(ws, error):
    print(error)

# Call the function for doing dictionary with markets IDs and last klines
get_markets_id()
if(len(MARKETS_IDS)==0):
    get_markets()
    get_markets_id()

get_last_candlesticks()

# Construct request params
request = json.dumps({
    "method": "SUBSCRIBE",
    "params": get_params(),
    "id": 1
})

i = 1
wsKlines = []
wsTicker = []
for params in get_params()['klines']:
    ws = websocket.create_connection("wss://fstream.binance.com/ws/")
    request = json.dumps({
        "method": "SUBSCRIBE",
        "params": params,
        "id": i
    })
    ws.send(request)
    wsKlines.append(ws)
    i += 1

for params in get_params()['ticker']:
    ws = websocket.create_connection("wss://fstream.binance.com/ws/")
    request = json.dumps({
        "method": "SUBSCRIBE",
        "params": params,
        "id": i
    })
    ws.send(request)
    wsTicker.append(ws)
    i += 1


# Create websocket connection and send params
request = json.dumps({
        "method": "SUBSCRIBE",
        "params": get_params()['klines'][0],
        "id": i
    })
ws = websocket.create_connection("wss://fstream.binance.com/ws/")
ws.send(request)

# Loop done to load markets data and save it in DB.
while True:
    timeout = time.time() + 2  # Will checkout data every 2 seconds.
    responseKlines = []
    while time.time() < timeout:
        try:
            responseKlines.append(ws.recv_frame())
        except:
            ws = websocket.create_connection("wss://fstream.binance.com/ws/")
            ws.send(request)
            break

    klines = []
    for kline in responseKlines:
        try:
            klines.append(json.loads(str(kline.data)[2:len(str(kline.data))-1])['k'])
        except:
            1
    insert_or_update_kline(klines)
