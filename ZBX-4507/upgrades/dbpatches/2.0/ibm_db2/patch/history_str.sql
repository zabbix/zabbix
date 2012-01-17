ALTER TABLE history_str ALTER COLUMN itemid SET WITH DEFAULT NULL
/
REORG TABLE history_str
/
ALTER TABLE history_str ADD ns integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE history_str
/
