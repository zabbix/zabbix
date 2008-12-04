alter table graphs_items modify color varchar2(32) DEFAULT '009600';

CREATE INDEX graphs_items_1 on graphs_items (itemid);
