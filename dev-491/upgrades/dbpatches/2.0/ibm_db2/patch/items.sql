ALTER TABLE items ALTER COLUMN itemid SET WITH DEFAULT NULL;
REORG TABLE items;
ALTER TABLE items ALTER COLUMN hostid SET WITH DEFAULT NULL;
REORG TABLE items;
ALTER TABLE items ALTER COLUMN units SET DATA TYPE varchar(255);
REORG TABLE items;
ALTER TABLE items ALTER COLUMN templateid SET WITH DEFAULT NULL;
REORG TABLE items;
ALTER TABLE items ALTER COLUMN templateid DROP NOT NULL;
REORG TABLE items;
ALTER TABLE items ALTER COLUMN valuemapid SET WITH DEFAULT NULL;
REORG TABLE items;
ALTER TABLE items ALTER COLUMN valuemapid DROP NOT NULL;
REORG TABLE items;
ALTER TABLE items ADD lastns integer NULL;
REORG TABLE items;
UPDATE items SET templateid=NULL WHERE templateid=0;
UPDATE items SET templateid=NULL WHERE NOT templateid IS NULL AND NOT templateid IN (SELECT itemid FROM items);
UPDATE items SET valuemapid=NULL WHERE valuemapid=0;
UPDATE items SET valuemapid=NULL WHERE NOT valuemapid IS NULL AND NOT valuemapid IN (SELECT valuemapid from valuemaps);
UPDATE items SET units='Bps' WHERE type=9 AND units='bps';
DELETE FROM items WHERE hostid NOT IN (SELECT hostid FROM hosts);
ALTER TABLE items ADD CONSTRAINT c_items_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
REORG TABLE items;
ALTER TABLE items ADD CONSTRAINT c_items_2 FOREIGN KEY (templateid) REFERENCES items (itemid) ON DELETE CASCADE;
REORG TABLE items;
ALTER TABLE items ADD CONSTRAINT c_items_3 FOREIGN KEY (valuemapid) REFERENCES valuemaps (valuemapid);
REORG TABLE items;
UPDATE items SET port=snmp_port;
REORG TABLE items;
ALTER TABLE items DROP COLUMN snmp_port;
REORG TABLE items;
-- host interface for non IPMI and non templated items
UPDATE items 
	SET interfaceid=(SELECT interfaceid FROM interface WHERE hostid=items.hostid AND main=1 AND itemtype=0)
	WHERE EXISTS(SELECT hostid FROM hosts WHERE hosts.hostid=items.hostid AND hosts.status IN (0,1))
		AND type<>12;

-- host interface for IPMI and non templated items
UPDATE items 
	SET interfaceid=(SELECT interfaceid FROM interface WHERE hostid=items.hostid AND main=1 AND itemtype=12)
	WHERE EXISTS(SELECT hostid FROM hosts WHERE hosts.hostid=items.hostid AND hosts.status IN (0,1))
		AND type=12;

-- keep port for SNMP items
UPDATE items
	SET port=(SELECT port FROM interface WHERE interface.interfaceid=items.interfaceid)
	WHERE port='' 
		AND interfaceid IS NOT NULL
		AND type IN (1,4,6);
REORG TABLE items;