ALTER TABLE functions MODIFY functionid bigint unsigned NOT NULL,
		      MODIFY itemid bigint unsigned NOT NULL,
		      MODIFY triggerid bigint unsigned NOT NULL,
		      DROP COLUMN lastvalue;
DELETE FROM functions WHERE NOT itemid IN (SELECT itemid FROM items);
DELETE FROM functions WHERE NOT triggerid IN (SELECT triggerid FROM triggers);
ALTER TABLE functions ADD CONSTRAINT c_functions_1 FOREIGN KEY (itemid) REFERENCES items (itemid) ON DELETE CASCADE;
ALTER TABLE functions ADD CONSTRAINT c_functions_2 FOREIGN KEY (triggerid) REFERENCES triggers (triggerid) ON DELETE CASCADE;
