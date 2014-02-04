ALTER TABLE screens_items MODIFY screenitemid DEFAULT NULL;
ALTER TABLE screens_items MODIFY screenid DEFAULT NULL;
ALTER TABLE screens_items ADD sort_triggers number(10) DEFAULT '0' NOT NULL;
DELETE FROM screens_items WHERE screenid NOT IN (SELECT screenid FROM screens);
ALTER TABLE screens_items ADD CONSTRAINT c_screens_items_1 FOREIGN KEY (screenid) REFERENCES screens (screenid) ON DELETE CASCADE;
