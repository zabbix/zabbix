ALTER TABLE proxy_history MODIFY itemid DEFAULT NULL;
ALTER TABLE proxy_history ADD ns number(10) DEFAULT '0' NOT NULL;
ALTER TABLE proxy_history ADD status number(10) DEFAULT '0' NOT NULL;
