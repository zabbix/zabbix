\COPY (SELECT itemid,concat(clock,'.',ns),value FROM history_text) TO '/tmp/history_text.csv' DELIMITER ',' CSV;
