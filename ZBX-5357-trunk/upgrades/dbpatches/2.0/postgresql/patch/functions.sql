ALTER TABLE ONLY functions ALTER functionid DROP DEFAULT,
			   ALTER itemid DROP DEFAULT,
			   ALTER triggerid DROP DEFAULT,
			   DROP COLUMN lastvalue;
DELETE FROM functions WHERE NOT EXISTS (SELECT 1 FROM items WHERE items.itemid=functions.itemid);
DELETE FROM functions WHERE NOT EXISTS (SELECT 1 FROM triggers WHERE triggers.triggerid=functions.triggerid);
ALTER TABLE ONLY functions ADD CONSTRAINT c_functions_1 FOREIGN KEY (itemid) REFERENCES items (itemid) ON DELETE CASCADE;
ALTER TABLE ONLY functions ADD CONSTRAINT c_functions_2 FOREIGN KEY (triggerid) REFERENCES triggers (triggerid) ON DELETE CASCADE;
