ALTER TABLE history_str
	MODIFY itemid bigint unsigned NOT NULL,
	ADD ns integer DEFAULT '0' NOT NULL;
