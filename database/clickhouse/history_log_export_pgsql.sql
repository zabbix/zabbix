\COPY (SELECT itemid,concat(clock,'.',ns),value,source,severity,logeventid,timestamp FROM history_log) TO '/tmp/history_log.csv' DELIMITER ',' CSV;
