ALTER TABLE history_uint MODIFY itemid bigint unsigned NOT NULL;
ALTER TABLE history_uint ADD ns integer DEFAULT '0' NOT NULL;
