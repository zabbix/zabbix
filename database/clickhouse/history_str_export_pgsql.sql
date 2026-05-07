\COPY (SELECT itemid,concat(clock,'.',LPAD(ns::text,9,'0')),value FROM history_str) TO '/tmp/history_str.csv' DELIMITER ',' CSV;
