ALTER TABLE history_log MODIFY itemid bigint unsigned NOT NULL;
ALTER TABLE history_log ADD ns integer DEFAULT '0' NOT NULL;
