SELECT itemid,value,concat(clock,'.',ns) FROM history_text
INTO OUTFILE '/tmp/history_text.csv'
FIELDS ENCLOSED BY '"'
TERMINATED BY ','
ESCAPED BY '"' LINES
TERMINATED BY '\r\n';
