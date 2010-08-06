ALTER TABLE ONLY history_text ALTER itemid DROP DEFAULT;
ALTER TABLE ONLY history_text ADD ns integer DEFAULT '0' NOT NULL;
