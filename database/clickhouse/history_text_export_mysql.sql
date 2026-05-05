SELECT itemid,concat(clock,'.',ns),value FROM history_text
INTO OUTFILE '/var/lib/mysql-files/history_text.csv'
FIELDS ENCLOSED BY '"'
TERMINATED BY ','
ESCAPED BY '"' LINES
TERMINATED BY '\r\n';
