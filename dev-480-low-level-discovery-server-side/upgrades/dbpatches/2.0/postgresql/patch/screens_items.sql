ALTER TABLE ONLY screens_items ALTER screenitemid DROP DEFAULT,
			       ALTER screenid DROP DEFAULT;
DELETE FROM screens_items WHERE screenid NOT IN (SELECT screenid FROM screens);
ALTER TABLE ONLY screens_items ADD CONSTRAINT c_screens_items_1 FOREIGN KEY (screenid) REFERENCES screens (screenid) ON DELETE CASCADE;
