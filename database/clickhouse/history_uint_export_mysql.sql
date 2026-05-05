SELECT itemid,concat(clock,'.',ns),value FROM history_uint
INTO OUTFILE '/var/lib/mysql-files/history_uint.csv'
FIELDS ENCLOSED BY '"'
TERMINATED BY ','
ESCAPED BY '"' LINES
TERMINATED BY '\r\n';
