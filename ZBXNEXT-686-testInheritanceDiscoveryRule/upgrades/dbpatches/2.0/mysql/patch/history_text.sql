ALTER TABLE history_text
	MODIFY id bigint unsigned NOT NULL,
	MODIFY itemid bigint unsigned NOT NULL,
	ADD ns integer DEFAULT '0' NOT NULL;
