alter table graphs_items change color color varchar(32) DEFAULT '009600' NOT NULL;

CREATE INDEX graphs_items_1 on graphs_items (itemid);
