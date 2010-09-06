ALTER TABLE ONLY history_uint ALTER itemid DROP DEFAULT;
ALTER TABLE ONLY history_uint ADD ns integer DEFAULT '0' NOT NULL;
