update graphs_items set color='FF0000' where color='Red';
update graphs_items set color='960000' where color='Dark Red';
update graphs_items set color='00FF00' where color='Green';
update graphs_items set color='009600' where color='Dark Green';
update graphs_items set color='0000FF' where color='Blue';
update graphs_items set color='000096' where color='Dark Blue';
update graphs_items set color='FFFF00' where color='Yellow';
update graphs_items set color='969600' where color='Dark Yellow';
update graphs_items set color='00FFFF' where color='Cyan';
update graphs_items set color='000000' where color='Black';
update graphs_items set color='969696' where color='Gray';
update graphs_items set color='FFFFFF' where color='White';
alter table graphs_items modify color varchar2(6) DEFAULT '009600';

CREATE INDEX graphs_items_1 on graphs_items (itemid);
CREATE INDEX graphs_items_2 on graphs_items (graphid);