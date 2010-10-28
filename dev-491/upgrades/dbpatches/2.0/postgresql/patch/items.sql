ALTER TABLE ONLY items ALTER itemid DROP DEFAULT,
		       ALTER hostid DROP DEFAULT,
		       ALTER units TYPE varchar(255),
		       ALTER templateid DROP DEFAULT,
		       ALTER templateid DROP NOT NULL,
		       ALTER valuemapid DROP DEFAULT,
		       ALTER valuemapid DROP NOT NULL,
		       ADD lastns integer NULL,
		       ADD flags integer DEFAULT '0' NOT NULL,
		       ADD filter varchar(255) DEFAULT '' NOT NULL,
			   ADD interfaceid bigint NULL,
			   ADD port varchar(64) DEFAULT '' NOT NULL;

UPDATE items SET templateid=NULL WHERE templateid=0;
UPDATE items SET templateid=NULL WHERE NOT templateid IS NULL AND NOT templateid IN (SELECT itemid FROM items);
UPDATE items SET valuemapid=NULL WHERE valuemapid=0;
UPDATE items SET valuemapid=NULL WHERE NOT valuemapid IS NULL AND NOT valuemapid IN (SELECT valuemapid from valuemaps);
UPDATE items SET units='Bps' WHERE type=9 AND units='bps';
DELETE FROM items WHERE NOT hostid IN (SELECT hostid FROM hosts);
ALTER TABLE ONLY items ADD CONSTRAINT c_items_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
ALTER TABLE ONLY items ADD CONSTRAINT c_items_2 FOREIGN KEY (templateid) REFERENCES items (itemid) ON DELETE CASCADE;
ALTER TABLE ONLY items ADD CONSTRAINT c_items_3 FOREIGN KEY (valuemapid) REFERENCES valuemaps (valuemapid);
ALTER TABLE ONLY items ADD CONSTRAINT c_items_4 FOREIGN KEY (interfaceid) REFERENCES interface (interfaceid);
UPDATE items SET port=snmp_port;
UPDATE items
	SET interfaceid=(SELECT interfaceid FROM interface WHERE hostid=items.hostid AND main=1);
	WHERE EXISTS(SELECT hostid FROM hosts WHERE hosts.hostid=items.hostid AND hosts.status IN (0,1));
UPDATE items
	SET port=(SELECT port FROM interface WHERE interface.interfaceid=items.interfaceid);
	WHERE port='' AND interfaceid NOT NULL;

ALTER TABLE items DROP COLUMN snmp_port;