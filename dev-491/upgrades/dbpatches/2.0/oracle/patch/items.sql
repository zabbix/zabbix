ALTER TABLE items MODIFY itemid DEFAULT NULL;
ALTER TABLE items MODIFY hostid DEFAULT NULL;
ALTER TABLE items MODIFY units nvarchar2(255);
ALTER TABLE items MODIFY templateid DEFAULT NULL;
ALTER TABLE items MODIFY templateid NULL;
ALTER TABLE items MODIFY valuemapid DEFAULT NULL;
ALTER TABLE items MODIFY valuemapid NULL;
ALTER TABLE items ADD lastns number(10) NULL;
ALTER TABLE items ADD flags number(10) DEFAULT '0' NOT NULL;
ALTER TABLE items ADD filter nvarchar2(255) DEFAULT '';
ALTER TABLE items ADD interfaceid number(20) DEFAULT NULL;
ALTER TABLE items ADD port nvarchar2(64) DEFAULT '';

UPDATE items SET templateid=NULL WHERE templateid=0;
UPDATE items SET templateid=NULL WHERE NOT templateid IS NULL AND NOT templateid IN (SELECT itemid FROM items);
UPDATE items SET valuemapid=NULL WHERE valuemapid=0;
UPDATE items SET valuemapid=NULL WHERE NOT valuemapid IS NULL AND NOT valuemapid IN (SELECT valuemapid from valuemaps);
UPDATE items SET units='Bps' WHERE type=9 AND units='bps';
DELETE FROM items WHERE NOT hostid IN (SELECT hostid FROM hosts);
ALTER TABLE items ADD CONSTRAINT c_items_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
ALTER TABLE items ADD CONSTRAINT c_items_2 FOREIGN KEY (templateid) REFERENCES items (itemid) ON DELETE CASCADE;
ALTER TABLE items ADD CONSTRAINT c_items_3 FOREIGN KEY (valuemapid) REFERENCES valuemaps (valuemapid);
ALTER TABLE items ADD CONSTRAINT c_items_4 FOREIGN KEY (interfaceid) REFERENCES interface (interfaceid);

UPDATE items SET port=snmp_port;
ALTER TABLE items DROP COLUMN snmp_port;

-- host interface for non IPMI and non templated items
UPDATE items 
	SET interfaceid=(SELECT interfaceid FROM interface WHERE hostid=items.hostid AND main=1 AND type=1)
	WHERE EXISTS(SELECT hostid FROM hosts WHERE hosts.hostid=items.hostid AND hosts.status IN (0,1))
		AND type<>12;

-- host interface for IPMI and non templated items
UPDATE items 
	SET interfaceid=(SELECT interfaceid FROM interface WHERE hostid=items.hostid AND main=1 AND type=3)
	WHERE EXISTS(SELECT hostid FROM hosts WHERE hosts.hostid=items.hostid AND hosts.status IN (0,1))
		AND type=12;

-- keep port for SNMP items
UPDATE items
	SET port=(SELECT port FROM interface WHERE interface.interfaceid=items.interfaceid)
	WHERE port='' 
		AND interfaceid IS NOT NULL
		AND type IN (1,4,6);

ALTER TABLE items MODIFY itemid DEFAULT NULL;
ALTER TABLE items MODIFY hostid DEFAULT NULL;
ALTER TABLE items MODIFY units nvarchar2(255);
ALTER TABLE items MODIFY templateid DEFAULT NULL;
ALTER TABLE items MODIFY templateid NULL;
ALTER TABLE items MODIFY valuemapid DEFAULT NULL;
ALTER TABLE items MODIFY valuemapid NULL;
ALTER TABLE items ADD lastns number(10) NULL;
ALTER TABLE items ADD flags number(10) DEFAULT '0' NOT NULL;
ALTER TABLE items ADD filter nvarchar2(255) DEFAULT '';
UPDATE items SET templateid=NULL WHERE templateid=0;
UPDATE items SET templateid=NULL WHERE NOT templateid IS NULL AND NOT templateid IN (SELECT itemid FROM items);
UPDATE items SET valuemapid=NULL WHERE valuemapid=0;
UPDATE items SET valuemapid=NULL WHERE NOT valuemapid IS NULL AND NOT valuemapid IN (SELECT valuemapid from valuemaps);
UPDATE items SET units='Bps' WHERE type=9 AND units='bps';
DELETE FROM items WHERE NOT hostid IN (SELECT hostid FROM hosts);
ALTER TABLE items ADD CONSTRAINT c_items_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
ALTER TABLE items ADD CONSTRAINT c_items_2 FOREIGN KEY (templateid) REFERENCES items (itemid) ON DELETE CASCADE;
ALTER TABLE items ADD CONSTRAINT c_items_3 FOREIGN KEY (valuemapid) REFERENCES valuemaps (valuemapid);
