\COPY (SELECT itemid,concat(clock,'.',LPAD(ns::text,9,'0')),value FROM history_json) TO '/tmp/history_json.csv' DELIMITER ',' CSV;
