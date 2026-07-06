\COPY (SELECT itemid,concat(clock,'.',LPAD(ns::text,9,'0')),value FROM history_uint) TO '/tmp/history_uint_clockns.csv' DELIMITER ',' CSV;
