\COPY (SELECT itemid,value,concat(clock,'.',ns) FROM history_uint) TO '/tmp/history_uint.csv' DELIMITER ',' CSV;
