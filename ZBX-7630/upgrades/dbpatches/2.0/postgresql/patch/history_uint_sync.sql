ALTER TABLE ONLY history_uint_sync
	ALTER itemid DROP DEFAULT,
	ALTER nodeid DROP DEFAULT,
	ALTER nodeid TYPE integer,
	ADD ns integer DEFAULT '0' NOT NULL;
