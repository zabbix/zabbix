ALTER TABLE graphs ALTER COLUMN graphid SET WITH DEFAULT NULL
/
REORG TABLE graphs
/
ALTER TABLE graphs ALTER COLUMN templateid SET WITH DEFAULT NULL
/
REORG TABLE graphs
/
ALTER TABLE graphs ALTER COLUMN templateid DROP NOT NULL
/
REORG TABLE graphs
/
ALTER TABLE graphs ALTER COLUMN ymin_itemid SET WITH DEFAULT NULL
/
REORG TABLE graphs
/
ALTER TABLE graphs ALTER COLUMN ymin_itemid DROP NOT NULL
/
REORG TABLE graphs
/
ALTER TABLE graphs ALTER COLUMN ymax_itemid SET WITH DEFAULT NULL
/
REORG TABLE graphs
/
ALTER TABLE graphs ALTER COLUMN ymax_itemid DROP NOT NULL
/
REORG TABLE graphs
/
ALTER TABLE graphs ALTER COLUMN show_legend SET DEFAULT 1
/
REORG TABLE graphs
/
ALTER TABLE graphs ADD flags integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE graphs
/
UPDATE graphs SET show_legend=1 WHERE graphtype IN (0, 1)
/
UPDATE graphs SET templateid=NULL WHERE templateid=0
/
UPDATE graphs SET templateid=NULL WHERE templateid IS NOT NULL AND templateid NOT IN (SELECT graphid FROM graphs)
/
UPDATE graphs SET ymin_itemid=NULL WHERE ymin_itemid=0 OR ymin_itemid NOT IN (SELECT itemid FROM items)
/
UPDATE graphs SET ymax_itemid=NULL WHERE ymax_itemid=0 OR ymax_itemid NOT IN (SELECT itemid FROM items)
/
UPDATE graphs SET ymin_type=0 WHERE ymin_type=2 AND ymin_itemid=NULL
/
UPDATE graphs SET ymax_type=0 WHERE ymax_type=2 AND ymax_itemid=NULL
/
ALTER TABLE graphs ADD CONSTRAINT c_graphs_1 FOREIGN KEY (templateid) REFERENCES graphs (graphid) ON DELETE CASCADE
/
ALTER TABLE graphs ADD CONSTRAINT c_graphs_2 FOREIGN KEY (ymin_itemid) REFERENCES items (itemid)
/
ALTER TABLE graphs ADD CONSTRAINT c_graphs_3 FOREIGN KEY (ymax_itemid) REFERENCES items (itemid)
/
