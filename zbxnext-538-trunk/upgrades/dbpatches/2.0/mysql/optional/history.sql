ALTER TABLE history MODIFY itemid bigint unsigned NOT NULL;
ALTER TABLE history ADD ns integer DEFAULT '0' NOT NULL;
