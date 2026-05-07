SELECT itemid,concat(clock,'.',LPAD(ns,9,'0')),value,source,severity,logeventid,timestamp FROM history_log
INTO OUTFILE '/var/lib/mysql-files/history_log.csv'
FIELDS ENCLOSED BY '"'
TERMINATED BY ','
ESCAPED BY '"' LINES
TERMINATED BY '\r\n';
