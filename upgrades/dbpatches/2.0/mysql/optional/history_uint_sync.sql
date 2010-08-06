ALTER TABLE history_uint_sync MODIFY itemid bigint unsigned NOT NULL;
ALTER TABLE history_uint_sync MODIFY nodeid integer NOT NULL;
ALTER TABLE history_uint_sync ADD ns integer DEFAULT '0' NOT NULL;
