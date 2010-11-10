ALTER TABLE ONLY items ALTER itemid DROP DEFAULT,
		       ALTER hostid DROP DEFAULT,
		       ALTER units TYPE varchar(255),
		       ALTER templateid DROP DEFAULT,
		       ALTER templateid DROP NOT NULL,
		       ALTER valuemapid DROP DEFAULT,
		       ALTER valuemapid DROP NOT NULL,
		       ADD lastns integer NULL,
		       ADD flags integer DEFAULT '0' NOT NULL,
		       ADD filter varchar(255) DEFAULT '' NOT NULL;
UPDATE items SET templateid=NULL WHERE templateid=0;
UPDATE items i1 SET templateid=NULL WHERE templateid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM items i2 WHERE i2.itemid=i1.templateid);
UPDATE items SET valuemapid=NULL WHERE valuemapid=0;
UPDATE items SET valuemapid=NULL WHERE valuemapid IS NOT NULL AND NOT EXISTS (SELECT 1 from valuemaps WHERE valuemaps.valuemapid=items.valuemapid);
UPDATE items SET units='Bps' WHERE type=9 AND units='bps';
DELETE FROM items WHERE NOT EXISTS (SELECT 1 FROM hosts WHERE hosts.hostid=items.hostid);
ALTER TABLE ONLY items ADD CONSTRAINT c_items_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
ALTER TABLE ONLY items ADD CONSTRAINT c_items_2 FOREIGN KEY (templateid) REFERENCES items (itemid) ON DELETE CASCADE;
ALTER TABLE ONLY items ADD CONSTRAINT c_items_3 FOREIGN KEY (valuemapid) REFERENCES valuemaps (valuemapid);
