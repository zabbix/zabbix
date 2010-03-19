alter table graphs add ymin_type               integer         DEFAULT '0'     NOT NULL;
alter table graphs add ymax_type               integer         DEFAULT '0'     NOT NULL;
alter table graphs add ymin_itemid             bigint unsigned         DEFAULT '0'     NOT NULL;
alter table graphs add ymax_itemid             bigint unsigned         DEFAULT '0'     NOT NULL;

update graphs set ymin_type=yaxistype;
update graphs set ymax_type=yaxistype;

alter table graphs drop yaxistype;
