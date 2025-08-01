\COPY (SELECT itemid,value,concat(clock,'.',ns) FROM history) TO '/tmp/history.csv' DELIMITER ',' CSV;
