ALTER TABLE history_text MODIFY itemid bigint unsigned NOT NULL;
ALTER TABLE history_text ADD ns integer DEFAULT '0' NOT NULL;
