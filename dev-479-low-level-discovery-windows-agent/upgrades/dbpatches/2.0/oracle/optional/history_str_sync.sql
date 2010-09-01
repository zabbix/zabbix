ALTER TABLE history_str_sync MODIFY itemid DEFAULT NULL;
ALTER TABLE history_str_sync MODIFY nodeid DEFAULT NULL;
ALTER TABLE history_str_sync MODIFY nodeid number(10);
ALTER TABLE history_str_sync ADD ns number(10) DEFAULT '0' NOT NULL;
