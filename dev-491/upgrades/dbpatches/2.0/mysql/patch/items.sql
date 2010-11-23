ALTER TABLE items MODIFY itemid bigint unsigned NOT NULL,
		  MODIFY hostid bigint unsigned NOT NULL,
		  MODIFY units varchar(255) DEFAULT '' NOT NULL,
		  MODIFY templateid bigint unsigned NULL,
		  MODIFY valuemapid bigint unsigned NULL,
		  ADD lastns integer NULL,
		  ADD flags integer DEFAULT '0' NOT NULL,
		  ADD filter varchar(255) DEFAULT '' NOT NULL,
		  ADD interfaceid bigint unsigned NULL,
		  ADD port varchar(64) DEFAULT '' NOT NULL;
		  
UPDATE items SET templateid=NULL WHERE templateid=0;
CREATE TEMPORARY TABLE tmp_items_itemid (itemid bigint unsigned PRIMARY KEY);

INSERT INTO tmp_items_itemid (itemid) (SELECT itemid FROM items);
UPDATE items SET templateid=NULL WHERE NOT templateid IS NULL AND NOT templateid IN (SELECT itemid FROM tmp_items_itemid);
DROP TABLE tmp_items_itemid;

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

