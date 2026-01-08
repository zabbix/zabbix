\COPY (SELECT itemid,concat(clock,'.',ns),value FROM history) TO '/tmp/history.csv' DELIMITER ',' CSV;
