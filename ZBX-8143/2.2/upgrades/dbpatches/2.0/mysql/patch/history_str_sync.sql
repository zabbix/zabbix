ALTER TABLE history_str_sync
	MODIFY itemid bigint unsigned NOT NULL,
	MODIFY nodeid integer NOT NULL,
	ADD ns integer DEFAULT '0' NOT NULL;
