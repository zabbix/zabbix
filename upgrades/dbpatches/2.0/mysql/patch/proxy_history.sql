ALTER TABLE proxy_history MODIFY itemid bigint unsigned NOT NULL;
ALTER TABLE proxy_history ADD ns integer DEFAULT '0' NOT NULL;
