ALTER TABLE ONLY history_uint_sync ALTER itemid DROP DEFAULT;
ALTER TABLE ONLY history_uint_sync ALTER nodeid DROP DEFAULT;
ALTER TABLE ONLY history_uint_sync ALTER nodeid TYPE integer;
ALTER TABLE ONLY history_uint_sync ADD ns integer DEFAULT '0' NOT NULL;
