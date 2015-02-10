ALTER TABLE history_uint ALTER COLUMN itemid SET WITH DEFAULT NULL
/
REORG TABLE history_uint
/
ALTER TABLE history_uint ADD ns integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE history_uint
/
