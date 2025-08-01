\COPY (SELECT itemid,value,concat(clock,'.',ns) FROM history_text) TO '/tmp/history_text.csv' DELIMITER ',' CSV;
