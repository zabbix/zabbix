ALTER TABLE ONLY graphs ALTER graphid DROP DEFAULT,
			ALTER templateid DROP DEFAULT,
			ALTER templateid DROP NOT NULL,
			ALTER ymin_itemid DROP DEFAULT,
			ALTER ymin_itemid DROP NOT NULL,
			ALTER ymax_itemid DROP DEFAULT,
			ALTER ymax_itemid DROP NOT NULL,
			ALTER show_legend SET DEFAULT 1,
			ADD flags integer DEFAULT '0' NOT NULL;
UPDATE graphs SET show_legend=1 WHERE graphtype=0 OR graphtype=1;
UPDATE graphs SET templateid=NULL WHERE templateid=0;
UPDATE graphs SET templateid=NULL WHERE templateid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM graphs g WHERE g.graphid=graphs.templateid);
UPDATE graphs SET ymin_itemid=NULL WHERE ymin_itemid=0 OR NOT EXISTS (SELECT itemid FROM items WHERE items.itemid=graphs.ymin_itemid);
UPDATE graphs SET ymax_itemid=NULL WHERE ymax_itemid=0 OR NOT EXISTS (SELECT itemid FROM items WHERE items.itemid=graphs.ymax_itemid);
UPDATE graphs SET ymin_type=0 WHERE ymin_type=2 AND ymin_itemid=NULL;
UPDATE graphs SET ymax_type=0 WHERE ymax_type=2 AND ymax_itemid=NULL;
ALTER TABLE ONLY graphs ADD CONSTRAINT c_graphs_1 FOREIGN KEY (templateid) REFERENCES graphs (graphid) ON DELETE CASCADE;
ALTER TABLE ONLY graphs ADD CONSTRAINT c_graphs_2 FOREIGN KEY (ymin_itemid) REFERENCES items (itemid);
ALTER TABLE ONLY graphs ADD CONSTRAINT c_graphs_3 FOREIGN KEY (ymax_itemid) REFERENCES items (itemid);
