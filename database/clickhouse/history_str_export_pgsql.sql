\COPY (SELECT itemid,concat(clock,'.',ns),value FROM history_str) TO '/tmp/history_str.csv' DELIMITER ',' CSV;
