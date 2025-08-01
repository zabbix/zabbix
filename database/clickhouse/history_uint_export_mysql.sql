SELECT itemid,value,concat(clock,'.',ns) FROM history_uint
INTO OUTFILE '/tmp/history_uint.csv'
FIELDS ENCLOSED BY '"'
TERMINATED BY ','
ESCAPED BY '"' LINES
TERMINATED BY '\r\n';
