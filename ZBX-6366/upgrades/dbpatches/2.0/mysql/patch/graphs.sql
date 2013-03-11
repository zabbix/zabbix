ALTER TABLE graphs MODIFY graphid bigint unsigned NOT NULL,
		   MODIFY templateid bigint unsigned NULL,
		   MODIFY ymin_itemid bigint unsigned NULL,
		   MODIFY ymax_itemid bigint unsigned NULL,
		   MODIFY show_legend integer NOT NULL DEFAULT 1,
		   ADD flags integer DEFAULT '0' NOT NULL;
UPDATE graphs SET templateid=NULL WHERE templateid=0;
UPDATE graphs SET show_legend=1 WHERE graphtype=0 OR graphtype=1;
CREATE TEMPORARY TABLE tmp_graphs_graphid (graphid bigint unsigned PRIMARY KEY);
INSERT INTO tmp_graphs_graphid (graphid) (SELECT graphid FROM graphs);
UPDATE graphs SET templateid=NULL WHERE NOT templateid IS NULL AND NOT templateid IN (SELECT graphid FROM tmp_graphs_graphid);
DROP TABLE tmp_graphs_graphid;
UPDATE graphs SET ymin_itemid=NULL WHERE ymin_itemid=0 OR NOT ymin_itemid IN (SELECT itemid FROM items);
UPDATE graphs SET ymax_itemid=NULL WHERE ymax_itemid=0 OR NOT ymax_itemid IN (SELECT itemid FROM items);
UPDATE graphs SET ymin_type=0 WHERE ymin_type=2 AND ymin_itemid=NULL;
UPDATE graphs SET ymax_type=0 WHERE ymax_type=2 AND ymax_itemid=NULL;
ALTER TABLE graphs ADD CONSTRAINT c_graphs_1 FOREIGN KEY (templateid) REFERENCES graphs (graphid) ON DELETE CASCADE;
ALTER TABLE graphs ADD CONSTRAINT c_graphs_2 FOREIGN KEY (ymin_itemid) REFERENCES items (itemid);
ALTER TABLE graphs ADD CONSTRAINT c_graphs_3 FOREIGN KEY (ymax_itemid) REFERENCES items (itemid);
