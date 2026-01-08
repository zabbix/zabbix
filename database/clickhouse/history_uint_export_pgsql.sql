\COPY (SELECT itemid,concat(clock,'.',ns),value FROM history_uint) TO '/tmp/history_uint.csv' DELIMITER ',' CSV;
