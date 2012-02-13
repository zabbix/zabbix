ALTER TABLE screens_items ALTER COLUMN screenitemid SET WITH DEFAULT NULL
/
REORG TABLE screens_items
/
ALTER TABLE screens_items ALTER COLUMN screenid SET WITH DEFAULT NULL
/
REORG TABLE screens_items
/
ALTER TABLE screens_items ADD sort_triggers integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE screens_items
/
DELETE FROM screens_items WHERE screenid NOT IN (SELECT screenid FROM screens)
/
ALTER TABLE screens_items ADD CONSTRAINT c_screens_items_1 FOREIGN KEY (screenid) REFERENCES screens (screenid) ON DELETE CASCADE
/
