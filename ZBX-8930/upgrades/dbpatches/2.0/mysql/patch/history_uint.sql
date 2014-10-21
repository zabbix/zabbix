ALTER TABLE history_uint
	MODIFY itemid bigint unsigned NOT NULL,
	ADD ns integer DEFAULT '0' NOT NULL;
