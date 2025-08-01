\COPY (SELECT itemid,value,concat(clock,'.',ns) FROM history_str) TO '/tmp/history_str.csv' DELIMITER ',' CSV;
