SELECT itemid,concat(clock,'.',ns),value FROM history
INTO OUTFILE '/var/lib/mysql-files/history.csv'
FIELDS ENCLOSED BY '"'
TERMINATED BY ','
ESCAPED BY '"' LINES
TERMINATED BY '\r\n';
