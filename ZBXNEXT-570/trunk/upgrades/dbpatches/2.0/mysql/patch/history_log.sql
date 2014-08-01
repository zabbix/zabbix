ALTER TABLE history_log
	MODIFY id bigint unsigned NOT NULL,
	MODIFY itemid bigint unsigned NOT NULL,
	ADD ns integer DEFAULT '0' NOT NULL;
