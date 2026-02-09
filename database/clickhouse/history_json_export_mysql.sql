SELECT itemid,concat(clock,'.',ns),value FROM history_json
INTO OUTFILE '/tmp/history_json.csv'
FIELDS ENCLOSED BY '"'
TERMINATED BY ','
ESCAPED BY '"' LINES
TERMINATED BY '\r\n';
