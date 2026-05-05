SELECT itemid,concat(clock,'.',ns),value FROM history_str
INTO OUTFILE '/var/lib/mysql-files/history_str.csv'
FIELDS ENCLOSED BY '"'
TERMINATED BY ','
ESCAPED BY '"' LINES
TERMINATED BY '\r\n';
