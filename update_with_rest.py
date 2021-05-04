import requests
import json
import mysql.connector
import time
import threading
import install_db

MARKETS_IDS = {}
LAST_OPEN_TIME_15m = 0
LAST_OPEN_TIME_1h = 0
LAST_OPEN_TIME_4h = 0

def get_last_times():
    sql15m = "SELECT MAX(open_time) FROM klines15ms"
    sql1h = "SELECT MAX(open_time) FROM klines1hs"
    sql4h = "SELECT MAX(open_time) FROM klines4hs"

    db = mysql.connector.connect(
        host="localhost", user="root", password="", database="cfdsm")
    cursor = db.cursor()

    result = []
    try:
        cursor.execute(sql15m)
        result.append(int(cursor.fetchall()[0][0].timestamp()/900)*900)
    except:
        result.append(0)

    try:
        cursor.execute(sql1h)
        result.append(int(cursor.fetchall()[0][0].timestamp()/3600)*3600)
    except:
        result.append(0)

    try:
        cursor.execute(sql4h)
        result.append(int(cursor.fetchall()[0][0].timestamp()/14400)*14400)
    except:
        result.append(0)

    return result

def get_markets():
    url = "https://fapi.binance.com/fapi/v1/ticker/price"
    req = requests.get(url).text
    markets = (json.loads(req))
    sql = """INSERT INTO markets (name, created_at, updated_at) VALUES """
    for market in markets:
        if(market["symbol"].find("_") < 0):
            sql += "('{}', CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP()),".format(market["symbol"])
    sql = sql[:len(sql)-1]
    sql += " ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP()"
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

def get_markets_id():
    db = mysql.connector.connect(
        host="localhost", user="root", password="", database="cfdsm")
    cursor = db.cursor()
    cursor.execute('SELECT name, id from markets')
    for market in cursor.fetchall():
        MARKETS_IDS.update({market[0]: market[1]})

def insert_update_db_prices(data):
    global LAST_OPEN_TIME_15m
    global LAST_OPEN_TIME_1h
    global LAST_OPEN_TIME_4h

    db = mysql.connector.connect(host="localhost", user="root", password="", database="cfdsm")
    cursor = db.cursor()

    insert15m = int(data[0]['time']/900000)*900 != LAST_OPEN_TIME_15m
    insert1h = int(data[0]['time']/3600000)*3600 != LAST_OPEN_TIME_1h
    insert4h = int(data[0]['time']/14400000)*14400 != LAST_OPEN_TIME_4h

    for ticker in data:
        #KLINES 15m
        try:
            if(insert15m):
                sql = """INSERT INTO klines15ms (market_id, open_time, close_time, open, close, high, low,
                            created_at, updated_at) VALUES
                            ({}, FROM_UNIXTIME({}), FROM_UNIXTIME({}), {}, {}, {}, {}, CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP())""".format(
                                MARKETS_IDS[ticker['symbol']], int(ticker['time']/900000)*900, int(ticker['time']/900000)*900 + 899,
                                ticker['price'], ticker['price'], ticker['price'], ticker['price']
                            )

                LAST_OPEN_TIME_15m = int(ticker['time']/900000)*900
            else:
                sql = """UPDATE klines15ms SET close = {} WHERE id =
                    (SELECT id FROM klines15ms WHERE market_id = {} ORDER BY open_time DESC LIMIT 1)""".format(
                        ticker['price'], MARKETS_IDS[ticker['symbol']]
                    )
            cursor.execute(sql)
        except:
            1
        #KLINES 1h
        try:
            if(insert1h):
                sql = """INSERT INTO klines1hs (market_id, open_time, close_time, open, close, high, low,
                            created_at, updated_at) VALUES
                            ({}, FROM_UNIXTIME({}), FROM_UNIXTIME({}), {}, {}, {}, {}, CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP())""".format(
                                MARKETS_IDS[ticker['symbol']], int(ticker['time']/3600000)*3600, int(ticker['time']/3600000)*3600 + 3599,
                                ticker['price'], ticker['price'], ticker['price'], ticker['price']
                            )

                LAST_OPEN_TIME_1h = int(ticker['time']/3600000)*3600

            else:
                sql = """UPDATE klines1hs SET close = {} WHERE id =
                    (SELECT id FROM klines1hs WHERE market_id = {} ORDER BY open_time DESC LIMIT 1)""".format(
                        ticker['price'], MARKETS_IDS[ticker['symbol']]
                    )
            cursor.execute(sql)
        except:
            1
        #KLINES 4h
        try:
            if(insert4h):
                sql = """INSERT INTO klines4hs (market_id, open_time, close_time, open, close, high, low,
                            created_at, updated_at) VALUES
                            ({}, FROM_UNIXTIME({}), FROM_UNIXTIME({}), {}, {}, {}, {}, CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP())""".format(
                                MARKETS_IDS[ticker['symbol']], int(ticker['time']/14400000)*14400, int(ticker['time']/14400000)*14400 + 14399,
                                ticker['price'], ticker['price'], ticker['price'], ticker['price']
                            )

                LAST_OPEN_TIME_4h = int(ticker['time']/14400000)*14400
            else:
                sql = """UPDATE klines4hs SET close = {} WHERE id =
                    (SELECT id FROM klines4hs WHERE market_id = {} ORDER BY open_time DESC LIMIT 1)""".format(
                        ticker['price'], MARKETS_IDS[ticker['symbol']]
                    )
            cursor.execute(sql)
        except:
            1
    db.commit()

def insert_update_db_ticker24(data):
    db = mysql.connector.connect(host="localhost", user="root", password="", database="cfdsm")
    cursor = db.cursor()
    for dat in data:
        try:
            sql = """INSERT INTO ticker24hs (market_id, last_price, price_change) VALUES ({},{},{})
                ON DUPLICATE KEY UPDATE last_price = VALUES(last_price), price_change = VALUES(price_change)""".format(
                MARKETS_IDS[dat["symbol"]], dat["lastPrice"], dat["priceChangePercent"]
            )
            cursor.execute(sql)
        except:
            1
    db.commit()



get_markets()
get_markets_id()

LAST_OPEN_TIME_15m, LAST_OPEN_TIME_1h, LAST_OPEN_TIME_4h =  get_last_times()

while True:
    req = json.loads(requests.get("https://fapi.binance.com/fapi/v1/ticker/price").text)
    req_24h = json.loads(requests.get("https://fapi.binance.com/fapi/v1/ticker/24hr").text)
    t = threading.Thread(target= insert_update_db_prices, args= (req, ))
    t.start()
    t_24h = threading.Thread(target=insert_update_db_ticker24, args=(req_24h,))
    t_24h.start()
    time.sleep(2)

