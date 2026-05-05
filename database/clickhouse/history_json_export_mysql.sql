SELECT itemid,concat(clock,'.',ns),value FROM history_json
INTO OUTFILE '/var/lib/mysql-files/history_json.csv'
FIELDS ENCLOSED BY '"'
TERMINATED BY ','
ESCAPED BY '"' LINES
TERMINATED BY '\r\n';
