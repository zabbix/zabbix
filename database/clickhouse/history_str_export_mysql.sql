SELECT itemid,value,concat(clock,'.',ns) FROM history_str
INTO OUTFILE '/tmp/history_str.csv'
FIELDS ENCLOSED BY '"'
TERMINATED BY ','
ESCAPED BY '"' LINES
TERMINATED BY '\r\n';
