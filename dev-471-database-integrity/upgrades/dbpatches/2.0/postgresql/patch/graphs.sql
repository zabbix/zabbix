ALTER TABLE ONLY graphs ALTER graphid DROP DEFAULT,
			ALTER templateid DROP DEFAULT,
			ALTER templateid DROP NOT NULL,
			ALTER ymin_itemid DROP DEFAULT,
			ALTER ymin_itemid DROP NOT NULL,
			ALTER ymax_itemid DROP DEFAULT,
			ALTER ymax_itemid DROP NOT NULL;
UPDATE graphs SET templateid=NULL WHERE templateid=0;
UPDATE graphs SET templateid=NULL WHERE NOT templateid IS NULL AND NOT templateid IN (SELECT graphid FROM graphs);
UPDATE graphs SET ymin_itemid=NULL WHERE ymin_itemid=0;
UPDATE graphs SET ymax_itemid=NULL WHERE ymax_itemid=0;
ALTER TABLE ONLY graphs ADD CONSTRAINT c_graphs_1 FOREIGN KEY (templateid) REFERENCES graphs (graphid) ON DELETE CASCADE;
ALTER TABLE ONLY graphs ADD CONSTRAINT c_graphs_2 FOREIGN KEY (ymin_itemid) REFERENCES items (itemid) ON DELETE CASCADE;
ALTER TABLE ONLY graphs ADD CONSTRAINT c_graphs_3 FOREIGN KEY (ymax_itemid) REFERENCES items (itemid) ON DELETE CASCADE;
