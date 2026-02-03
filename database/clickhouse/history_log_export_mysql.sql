SELECT itemid,concat(clock,'.',ns),value,source,severity,logeventid,timestamp FROM history_log
INTO OUTFILE '/tmp/history_log.csv'
FIELDS ENCLOSED BY '"'
TERMINATED BY ','
ESCAPED BY '"' LINES
TERMINATED BY '\r\n';
