\COPY (SELECT itemid,concat(clock,'.',LPAD(ns::text,9,'0')),value,source,severity,logeventid,timestamp FROM history_log) TO '/tmp/history_log.csv' DELIMITER ',' CSV;
