ALTER TABLE history_uint_sync MODIFY itemid DEFAULT NULL;
ALTER TABLE history_uint_sync MODIFY nodeid DEFAULT NULL;
ALTER TABLE history_uint_sync MODIFY nodeid number(10);
ALTER TABLE history_uint_sync ADD ns number(10) DEFAULT '0' NOT NULL;
