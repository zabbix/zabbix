ALTER TABLE items MODIFY itemid bigint unsigned NOT NULL,
		  MODIFY hostid bigint unsigned NOT NULL,
		  MODIFY units varchar(255) DEFAULT '' NOT NULL,
		  MODIFY templateid bigint unsigned NULL,
		  MODIFY valuemapid bigint unsigned NULL,
		  ADD lastns integer NULL,
		  ADD flags integer DEFAULT '0' NOT NULL,
		  ADD filter varchar(255) DEFAULT '' NOT NULL;
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
