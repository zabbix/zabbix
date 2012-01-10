ALTER TABLE ONLY proxy_history ALTER itemid DROP DEFAULT;
ALTER TABLE ONLY proxy_history ADD ns integer DEFAULT '0' NOT NULL;
