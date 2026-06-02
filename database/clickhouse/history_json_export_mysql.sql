SELECT itemid,concat(clock,'.',LPAD(ns,9,'0')),IF(JSON_TYPE(value)='OBJECT',CAST(value AS CHAR),'null'),IF(JSON_TYPE(value)='OBJECT','',CAST(value AS CHAR)) FROM history_json
INTO OUTFILE '/var/lib/mysql-files/history_json_clockns.csv'
FIELDS ENCLOSED BY '"'
TERMINATED BY ','
ESCAPED BY '"' LINES
TERMINATED BY '\r\n';
