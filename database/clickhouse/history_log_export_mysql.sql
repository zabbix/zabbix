SELECT itemid,value,source,severity,logeventid,timestamp,concat(clock,'.',ns) FROM history_log
INTO OUTFILE '/tmp/history_log.csv'
FIELDS ENCLOSED BY '"'
TERMINATED BY ','
ESCAPED BY '"' LINES
TERMINATED BY '\r\n';
