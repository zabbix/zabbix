-- nclob can be slow with high number of items and items preprocessing, to improve performance it can be converted to nvarchar2(4000)
-- Following select queries should be executed to find out templates that must be removed before converting nclob to nvarchar2(4000):
-- select h.name,i.name from item_preproc pp,items i,hosts h where pp.itemid=i.itemid and i.hostid=h.hostid and length(pp.params) > 4000;
-- select h.name,i.name from items i,hosts h where i.hostid=h.hostid and length(i.params) > 4000;
-- Alternatively if MAX_STRING_SIZE is set then it's possible to change nvarchar2(4000) to nvarchar2(32767) in following queries
ALTER TABLE items RENAME COLUMN params TO zbx_old_tmp;
ALTER TABLE items ADD params nvarchar2(4000) DEFAULT '';
UPDATE items SET params=zbx_old_tmp;
ALTER TABLE items DROP COLUMN zbx_old_tmp;

ALTER TABLE items RENAME COLUMN description TO zbx_old_tmp;
ALTER TABLE items ADD description nvarchar2(4000) DEFAULT '';
UPDATE items SET description=zbx_old_tmp;
ALTER TABLE items DROP COLUMN zbx_old_tmp;

ALTER TABLE items RENAME COLUMN posts TO zbx_old_tmp;
ALTER TABLE items ADD posts nvarchar2(4000) DEFAULT '';
UPDATE items SET posts=zbx_old_tmp;
ALTER TABLE items DROP COLUMN zbx_old_tmp;

ALTER TABLE items RENAME COLUMN headers TO zbx_old_tmp;
ALTER TABLE items ADD headers nvarchar2(4000) DEFAULT '';
UPDATE items SET headers=zbx_old_tmp;
ALTER TABLE items DROP COLUMN zbx_old_tmp;

ALTER TABLE item_preproc RENAME COLUMN params TO zbx_old_tmp;
ALTER TABLE item_preproc ADD params nvarchar2(4000) DEFAULT '';
UPDATE item_preproc SET params=zbx_old_tmp;
ALTER TABLE item_preproc DROP COLUMN zbx_old_tmp;
