ALTER TABLE graphs_items
	MODIFY gitemid bigint unsigned NOT NULL,
	MODIFY graphid bigint unsigned NOT NULL,
	MODIFY itemid bigint unsigned NOT NULL,
	DROP COLUMN periods_cnt;
UPDATE graphs_items SET type=0 WHERE type=1;
DELETE FROM graphs_items WHERE NOT graphid IN (SELECT graphid FROM graphs);
DELETE FROM graphs_items WHERE NOT itemid IN (SELECT itemid FROM items);
ALTER TABLE graphs_items ADD CONSTRAINT c_graphs_items_1 FOREIGN KEY (graphid) REFERENCES graphs (graphid) ON DELETE CASCADE;
ALTER TABLE graphs_items ADD CONSTRAINT c_graphs_items_2 FOREIGN KEY (itemid) REFERENCES items (itemid) ON DELETE CASCADE;
