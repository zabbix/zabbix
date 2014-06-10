ALTER TABLE proxy_history
	MODIFY itemid bigint unsigned NOT NULL,
	MODIFY value longtext NOT NULL,
	ADD ns integer DEFAULT '0' NOT NULL,
	ADD status integer DEFAULT '0' NOT NULL;
