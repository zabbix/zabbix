alter table graphs add ymin_type               number(10)              DEFAULT '0'     NOT NULL;
alter table graphs add ymax_type               number(10)              DEFAULT '0'     NOT NULL;
alter table graphs add ymin_itemid             number(20)              DEFAULT '0'     NOT NULL;
alter table graphs add ymax_itemid             number(20)              DEFAULT '0'     NOT NULL;

update graphs set ymin_type=yaxistype;
update graphs set ymax_type=yaxistype;

alter table graphs drop column yaxistype;

alter table graphs modify name            nvarchar2(128)          DEFAULT '';
