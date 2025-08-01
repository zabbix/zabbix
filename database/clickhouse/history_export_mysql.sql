SELECT itemid,value,concat(clock,'.',ns) FROM history
INTO OUTFILE '/tmp/history.csv'
FIELDS ENCLOSED BY '"'
TERMINATED BY ','
ESCAPED BY '"' LINES
TERMINATED BY '\r\n';
