ALTER TABLE ONLY graphs_items
	ALTER gitemid DROP DEFAULT,
	ALTER graphid DROP DEFAULT,
	ALTER itemid DROP DEFAULT,
	DROP COLUMN periods_cnt;
UPDATE graphs_items SET type=0 WHERE type=1;
DELETE FROM graphs_items WHERE NOT EXISTS (SELECT 1 FROM graphs WHERE graphs.graphid=graphs_items.graphid);
DELETE FROM graphs_items WHERE NOT EXISTS (SELECT 1 FROM items WHERE items.itemid=graphs_items.itemid);
ALTER TABLE ONLY graphs_items ADD CONSTRAINT c_graphs_items_1 FOREIGN KEY (graphid) REFERENCES graphs (graphid) ON DELETE CASCADE;
ALTER TABLE ONLY graphs_items ADD CONSTRAINT c_graphs_items_2 FOREIGN KEY (itemid) REFERENCES items (itemid) ON DELETE CASCADE;
