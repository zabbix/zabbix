SELECT itemid,concat(clock,'.',LPAD(ns,9,'0')),value FROM history
INTO OUTFILE '/var/lib/mysql-files/history_clockns.csv'
FIELDS ENCLOSED BY '"'
TERMINATED BY ','
ESCAPED BY '"' LINES
TERMINATED BY '\r\n';
