\COPY (SELECT itemid,concat(clock,'.',LPAD(ns::text,9,'0')),value FROM history_text) TO '/tmp/history_text_clockns.csv' DELIMITER ',' CSV;
