ALTER TABLE history_uint_sync ALTER COLUMN itemid SET WITH DEFAULT NULL;
ALTER TABLE history_uint_sync ALTER COLUMN nodeid SET WITH DEFAULT NULL;
ALTER TABLE history_uint_sync ALTER COLUMN nodeid SET integer;
ALTER TABLE history_uint_sync ADD ns integer SET WITH DEFAULT '0' NOT NULL;
