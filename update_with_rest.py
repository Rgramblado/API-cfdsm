import requests
import json
import mysql.connector
import time
import threading
import install_db

MARKETS_IDS = {}


def get_markets_id():
    db = mysql.connector.connect(
        host="localhost", user="root", password="", database="cfdsm")
    cursor = db.cursor()
    cursor.execute('SELECT name, id from markets')
    for market in cursor.fetchall():
        MARKETS_IDS.update({market[0]: market[1]})


def insert_update_db_prices(data):
    db = mysql.connector.connect(
        host="localhost", user="root", password="", database="cfdsm")
    cursor = db.cursor()
    actualTime = int(time.time())
    for price in data:
        if not price['symbol'] in MARKETS_IDS:
            continue
        sql15m = """INSERT INTO klines15ms (market_id, open_time, close_time, open, close, high, low)
            VALUES({}, FROM_UNIXTIME({}), FROM_UNIXTIME({}), {}, {}, {}, {}) ON DUPLICATE KEY
            UPDATE close = VALUES(close)""".format(
            MARKETS_IDS[price['symbol']], int(
                actualTime/900)*900, int(actualTime/900)*900 + 899,
            price["price"], price["price"], price["price"], price["price"])
        sql1h = """INSERT INTO klines1hs (market_id, open_time, close_time, open, close, high, low)
            VALUES({}, FROM_UNIXTIME({}), FROM_UNIXTIME({}), {}, {}, {}, {}) ON DUPLICATE KEY
            UPDATE close = VALUES(close)""".format(
            MARKETS_IDS[price['symbol']], int(
                actualTime/3600)*3600, int(actualTime/3600)*3600 + 3599,
            price["price"], price["price"], price["price"], price["price"])
        sql4h = """INSERT INTO klines4hs (market_id, open_time, close_time, open, close, high, low)
            VALUES({}, FROM_UNIXTIME({}), FROM_UNIXTIME({}), {}, {}, {}, {}) ON DUPLICATE KEY
            UPDATE close = VALUES(close)""".format(
            MARKETS_IDS[price['symbol']], int(
                actualTime/14400)*14400, int(actualTime/14400)*14400 + 14399,
            price["price"], price["price"], price["price"], price["price"])
        cursor.execute(sql15m)
        cursor.execute(sql1h)
        cursor.execute(sql4h)

    db.commit()


def insert_update_db_ticker24(data):
    db = mysql.connector.connect(
        host="localhost", user="root", password="", database="cfdsm")
    cursor = db.cursor()
    for dat in data:
        try:
            sql = """INSERT INTO ticker24hs (market_id, last_price, price_change) VALUES ({},{},{})
                ON DUPLICATE KEY UPDATE last_price = VALUES(last_price), price_change = VALUES(price_change)""".format(
                MARKETS_IDS[dat["symbol"]
                            ], dat["lastPrice"], dat["priceChangePercent"]
            )
            cursor.execute(sql)
        except:
            1
    db.commit()


get_markets_id()

while True:
    req = json.loads(requests.get(
        "https://fapi.binance.com/fapi/v1/ticker/price").text)
    req_24h = json.loads(requests.get(
        "https://fapi.binance.com/fapi/v1/ticker/24hr").text)
    t = threading.Thread(target=insert_update_db_prices, args=(req, ))
    t.start()
    t_24h = threading.Thread(target=insert_update_db_ticker24, args=(req_24h,))
    t_24h.start()
    time.sleep(2)
