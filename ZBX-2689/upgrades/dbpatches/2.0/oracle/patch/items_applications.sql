ALTER TABLE items_applications MODIFY itemappid DEFAULT NULL;
ALTER TABLE items_applications MODIFY applicationid DEFAULT NULL;
ALTER TABLE items_applications MODIFY itemid DEFAULT NULL;
DROP INDEX items_applications_1;
DELETE FROM items_applications WHERE applicationid NOT IN (SELECT applicationid FROM applications);
DELETE FROM items_applications WHERE itemid NOT IN (SELECT itemid FROM items);
CREATE UNIQUE INDEX items_applications_1 ON items_applications (applicationid,itemid);
ALTER TABLE items_applications ADD CONSTRAINT c_items_applications_1 FOREIGN KEY (applicationid) REFERENCES applications (applicationid) ON DELETE CASCADE;
ALTER TABLE items_applications ADD CONSTRAINT c_items_applications_2 FOREIGN KEY (itemid) REFERENCES items (itemid) ON DELETE CASCADE;
