ALTER TABLE screens_items
	MODIFY screenitemid bigint unsigned NOT NULL,
	MODIFY screenid bigint unsigned NOT NULL,
	ADD sort_triggers integer DEFAULT '0' NOT NULL;
DELETE FROM screens_items WHERE screenid NOT IN (SELECT screenid FROM screens);
ALTER TABLE screens_items ADD CONSTRAINT c_screens_items_1 FOREIGN KEY (screenid) REFERENCES screens (screenid) ON DELETE CASCADE;
