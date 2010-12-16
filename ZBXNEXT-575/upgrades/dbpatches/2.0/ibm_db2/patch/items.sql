ALTER TABLE items ALTER COLUMN itemid SET WITH DEFAULT NULL
/
REORG TABLE items
/
ALTER TABLE items ALTER COLUMN hostid SET WITH DEFAULT NULL
/
REORG TABLE items
/
ALTER TABLE items ALTER COLUMN units SET DATA TYPE varchar(255)
/
REORG TABLE items
/
ALTER TABLE items ALTER COLUMN templateid SET WITH DEFAULT NULL
/
REORG TABLE items
/
ALTER TABLE items ALTER COLUMN templateid DROP NOT NULL
/
REORG TABLE items
/
ALTER TABLE items ALTER COLUMN valuemapid SET WITH DEFAULT NULL
/
REORG TABLE items
/
ALTER TABLE items ALTER COLUMN valuemapid DROP NOT NULL
/
REORG TABLE items
/
ALTER TABLE items ADD lastns integer NULL
/
REORG TABLE items
/
UPDATE items SET templateid=NULL WHERE templateid=0
/
UPDATE items SET templateid=NULL WHERE NOT templateid IS NULL AND NOT templateid IN (SELECT itemid FROM items)
/
UPDATE items SET valuemapid=NULL WHERE valuemapid=0
/
UPDATE items SET valuemapid=NULL WHERE NOT valuemapid IS NULL AND NOT valuemapid IN (SELECT valuemapid from valuemaps)
/
UPDATE items SET units='Bps' WHERE type=9 AND units='bps'
/
DELETE FROM items WHERE NOT hostid IN (SELECT hostid FROM hosts)
/
ALTER TABLE items ADD CONSTRAINT c_items_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE
/
REORG TABLE items
/
ALTER TABLE items ADD CONSTRAINT c_items_2 FOREIGN KEY (templateid) REFERENCES items (itemid) ON DELETE CASCADE
/
REORG TABLE items
/
ALTER TABLE items ADD CONSTRAINT c_items_3 FOREIGN KEY (valuemapid) REFERENCES valuemaps (valuemapid)
/
REORG TABLE items
/
