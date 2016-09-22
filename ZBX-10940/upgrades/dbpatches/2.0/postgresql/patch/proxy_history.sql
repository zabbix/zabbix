ALTER TABLE ONLY proxy_history
	ALTER itemid DROP DEFAULT,
	ADD ns integer DEFAULT '0' NOT NULL,
	ADD status integer DEFAULT '0' NOT NULL;
