\COPY (SELECT itemid,value,source,severity,logeventid,timestamp,concat(clock,'.',ns) FROM history_log) TO '/tmp/history_log.csv' DELIMITER ',' CSV;
