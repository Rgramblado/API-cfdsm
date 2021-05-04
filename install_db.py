import requests
import json
import time
import mysql.connector
import datetime

START_TIME = 1609459200000
LAST_TIME_IN_MARKET_15m = START_TIME
LAST_TIME_IN_MARKET_1h = START_TIME
LAST_TIME_IN_MARKET_4h = START_TIME
MARKETS_IDS = {}

def get_markets():
    url = "https://fapi.binance.com/fapi/v1/exchangeInfo"
    req = requests.get(url).text
    markets = (json.loads(req))["symbols"]
    sql = """INSERT INTO markets (name, start_time, created_at, updated_at) VALUES """
    for market in markets:
        if(market["contractType"] != "PERPETUAL"):
            continue
        sql += """('""" + market["symbol"] + """', FROM_UNIXTIME(""" + str(int(
            market["onboardDate"])/1000) + """), CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP()),"""
    sql = sql[:len(sql)-1]
    sql += """ ON DUPLICATE KEY UPDATE updated_at=CURRENT_TIMESTAMP()"""
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

def insert_kline(symbol, interval, data):
    db = mysql.connector.connect(host="localhost", user="root", password="", database="cfdsm")
    sql = """INSERT INTO klines{}s (market_id, open_time, close_time, open, close, high, low)
        VALUES """.format(interval)
    for dat in data:
        sql +=  """({}, FROM_UNIXTIME({}), FROM_UNIXTIME({}), {}, {}, {}, {}), """.format(
            MARKETS_IDS[symbol], int(dat[0])/1000, int(dat[6])/1000, dat[1], dat[4], dat[2], dat[3]
        )

    sql = sql[0:len(sql)-2]

    sql += """ ON DUPLICATE KEY UPDATE close = VALUES(close)"""
    cursor = db.cursor()
    cursor.execute(sql)
    db.commit()


get_markets()
get_markets_id()

while True:
    all_up_to_date = True
    for key in MARKETS_IDS:
        done_15m = False
        done_1h = False
        done_4h = False

        LAST_TIME_IN_MARKET_15m = START_TIME
        LAST_TIME_IN_MARKET_1h = START_TIME
        LAST_TIME_IN_MARKET_4h = START_TIME

        db = mysql.connector.connect(host="localhost", user="root", password="", database="cfdsm")
        cursor = db.cursor()

        cursor.execute("SELECT MAX(open_time) FROM klines15ms WHERE market_id={}".format(MARKETS_IDS[key]))
        LAST_TIME_DB_15m = cursor.fetchone()[0]
        if(LAST_TIME_DB_15m):
            LAST_TIME_IN_MARKET_15m = LAST_TIME_DB_15m.timestamp()
            if(LAST_TIME_IN_MARKET_15m + 899 > int(time.time())):
                done_15m = True

        cursor.execute("SELECT MAX(open_time) FROM klines1hs WHERE market_id={}".format(MARKETS_IDS[key]))
        LAST_TIME_DB_1h = cursor.fetchone()[0]
        if(LAST_TIME_DB_1h):
            LAST_TIME_IN_MARKET_1h = LAST_TIME_DB_1h.timestamp()
            if(LAST_TIME_IN_MARKET_1h + 3599 > int(time.time())):
                done_1h = True

        cursor.execute("SELECT MAX(open_time) FROM klines4hs WHERE market_id={}".format(MARKETS_IDS[key]))
        LAST_TIME_DB_4h = cursor.fetchone()[0]
        if(LAST_TIME_DB_4h):
            LAST_TIME_IN_MARKET_4h = LAST_TIME_DB_4h.timestamp()
            if(LAST_TIME_IN_MARKET_4h + 14399 > int(time.time())):
                done_4h = True


        while True:
            if (not done_15m):
                all_up_to_date = False
                try:
                    req = requests.get("https://fapi.binance.com/fapi/v1/klines?symbol={}&interval=15m&startTime={}&limit={}".format(
                        key, LAST_TIME_IN_MARKET_15m, 1500)).text
                    req = json.loads(req)
                    LAST_TIME_IN_MARKET_15m = req[len(req)-1][0]
                    if(req[len(req)-1][6]/1000 > time.time()):
                        done_15m = True
                        print("Done " + key + " 15m")
                    insert_kline(key, "15m", req)
                except:
                    done_15m = True
                time.sleep(0.3)

            if (not done_1h):
                all_up_to_date = False
                try:
                    req = requests.get("https://fapi.binance.com/fapi/v1/klines?symbol={}&interval=1h&startTime={}&limit={}".format(
                        key, LAST_TIME_IN_MARKET_1h, 1500)).text
                    req = json.loads(req)
                    LAST_TIME_IN_MARKET_1h = req[len(req)-1][0]
                    if(req[len(req)-1][6]/1000 > time.time()):
                        done_1h = True
                        print("Done " + key + " 1h")
                    insert_kline(key, "1h", req)
                except:
                    done_1h = True
                time.sleep(0.3)

            if (not done_4h):
                all_up_to_date = False
                try:
                    req = requests.get("https://fapi.binance.com/fapi/v1/klines?symbol={}&interval=4h&startTime={}&limit={}".format(
                        key, LAST_TIME_IN_MARKET_4h, 1500)).text
                    req = json.loads(req)
                    LAST_TIME_IN_MARKET_4h = req[len(req)-1][0]
                    if(req[len(req)-1][6]/1000 > time.time()):
                        done_4h = True
                        print("Done " + key + " 4h")
                    insert_kline(key, "4h", req)
                except:
                    done_4h = True
                time.sleep(0.3)

            if(done_15m and done_1h and done_4h):
                break

    if(all_up_to_date):
        break

print("All klines up to date")



