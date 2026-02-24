\COPY (SELECT itemid,concat(clock,'.',ns),value FROM history_json) TO '/tmp/history_json.csv' DELIMITER ',' CSV;
