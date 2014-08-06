ALTER TABLE ONLY screens_items
	ALTER screenitemid DROP DEFAULT,
	ALTER screenid DROP DEFAULT,
	ADD sort_triggers integer DEFAULT '0' NOT NULL;
DELETE FROM screens_items WHERE NOT EXISTS (SELECT 1 FROM screens WHERE screens.screenid=screens_items.screenid);
ALTER TABLE ONLY screens_items ADD CONSTRAINT c_screens_items_1 FOREIGN KEY (screenid) REFERENCES screens (screenid) ON DELETE CASCADE;
