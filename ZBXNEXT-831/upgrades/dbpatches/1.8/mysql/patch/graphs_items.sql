alter table graphs_items change color color varchar(6) DEFAULT '009600' NOT NULL;

CREATE INDEX graphs_items_1 on graphs_items (itemid);
CREATE INDEX graphs_items_2 on graphs_items (graphid);
