ALTER TABLE history_uint_sync ALTER COLUMN itemid SET WITH DEFAULT NULL
/
REORG TABLE history_uint_sync
/
ALTER TABLE history_uint_sync ALTER COLUMN nodeid SET WITH DEFAULT NULL
/
REORG TABLE history_uint_sync
/
ALTER TABLE history_uint_sync ALTER COLUMN nodeid SET DATA TYPE integer
/
REORG TABLE history_uint_sync
/
ALTER TABLE history_uint_sync ADD ns integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE history_uint_sync
/
