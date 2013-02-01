ALTER TABLE ONLY items_applications ALTER itemappid DROP DEFAULT,
				    ALTER applicationid DROP DEFAULT,
				    ALTER itemid DROP DEFAULT;
DROP INDEX items_applications_1;
DELETE FROM items_applications WHERE NOT EXISTS (SELECT 1 FROM applications WHERE applications.applicationid=items_applications.applicationid);
DELETE FROM items_applications WHERE NOT EXISTS (SELECT 1 FROM items WHERE items.itemid=items_applications.itemid);
CREATE UNIQUE INDEX items_applications_1 ON items_applications (applicationid,itemid);
ALTER TABLE ONLY items_applications ADD CONSTRAINT c_items_applications_1 FOREIGN KEY (applicationid) REFERENCES applications (applicationid) ON DELETE CASCADE;
ALTER TABLE ONLY items_applications ADD CONSTRAINT c_items_applications_2 FOREIGN KEY (itemid) REFERENCES items (itemid) ON DELETE CASCADE;
