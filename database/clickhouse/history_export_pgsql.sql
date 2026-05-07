\COPY (SELECT itemid,concat(clock,'.',LPAD(ns::text,9,'0')),value FROM history) TO '/tmp/history.csv' DELIMITER ',' CSV;
