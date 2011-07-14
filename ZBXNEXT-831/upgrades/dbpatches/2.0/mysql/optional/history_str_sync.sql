ALTER TABLE history_str_sync MODIFY itemid bigint unsigned NOT NULL;
ALTER TABLE history_str_sync MODIFY nodeid integer NOT NULL;
ALTER TABLE history_str_sync ADD ns integer DEFAULT '0' NOT NULL;
