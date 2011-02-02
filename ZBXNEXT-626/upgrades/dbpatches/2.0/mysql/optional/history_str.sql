ALTER TABLE history_str MODIFY itemid bigint unsigned NOT NULL;
ALTER TABLE history_str ADD ns integer DEFAULT '0' NOT NULL;
