SELECT itemid,concat(clock,'.',LPAD(ns,9,'0')),value FROM history_json
INTO OUTFILE '/var/lib/mysql-files/history_json.csv'
FIELDS ENCLOSED BY '"'
TERMINATED BY ','
ESCAPED BY '"' LINES
TERMINATED BY '\r\n';
