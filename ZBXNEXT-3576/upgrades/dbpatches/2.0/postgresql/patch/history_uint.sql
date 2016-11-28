ALTER TABLE ONLY history_uint
	ALTER itemid DROP DEFAULT,
	ADD ns integer DEFAULT '0' NOT NULL;
