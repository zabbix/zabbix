alter table graphs_items modify color           nvarchar2(6)            DEFAULT '009600';

CREATE INDEX graphs_items_1 on graphs_items (itemid);
CREATE INDEX graphs_items_2 on graphs_items (graphid);

alter table graphs_items modify color           nvarchar2(6)            DEFAULT '009600';
