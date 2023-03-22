-- nclob can be slow with high number of items and items preprocessing, to improve performance it can be converted to nvarchar2(4000)
-- Following select queries should be executed to find out templates that must be removed before converting nclob to nvarchar2(4000):
-- select h.name,i.name from item_preproc pp,items i,hosts h where pp.itemid=i.itemid and i.hostid=h.hostid and length(pp.params) > 4000;
-- select h.name,i.name from items i,hosts h where i.hostid=h.hostid and length(i.params) > 4000;
-- Alternatively if MAX_STRING_SIZE is set then it's possible to change nvarchar2(4000) to nvarchar2(32767) in following queries
alter table items rename column params to zbx_old_tmp;
alter table items add params nvarchar2(4000) default '';
update items set params=zbx_old_tmp;
alter table items drop column zbx_old_tmp;

alter table items rename column description to zbx_old_tmp;
alter table items add description nvarchar2(4000) default '';
update items set description=zbx_old_tmp;
alter table items drop column zbx_old_tmp;

alter table items rename column posts to zbx_old_tmp;
alter table items add posts nvarchar2(4000) default '';
update items set posts=zbx_old_tmp;
alter table items drop column zbx_old_tmp;

alter table items rename column headers to zbx_old_tmp;
alter table items add headers nvarchar2(4000) default '';
update items set headers=zbx_old_tmp;
alter table items drop column zbx_old_tmp;

alter table item_preproc rename column params to zbx_old_tmp;
alter table item_preproc add params nvarchar2(4000) default '';
update item_preproc set params=zbx_old_tmp;
alter table item_preproc drop column zbx_old_tmp;
