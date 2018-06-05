ALTER TABLE httptestitem MODIFY httptestitemid DEFAULT NULL;
ALTER TABLE httptestitem MODIFY httptestid DEFAULT NULL;
ALTER TABLE httptestitem MODIFY itemid DEFAULT NULL;
DELETE FROM httptestitem WHERE NOT httptestid IN (SELECT httptestid FROM httptest);
DELETE FROM httptestitem WHERE NOT itemid IN (SELECT itemid FROM items);
ALTER TABLE httptestitem ADD CONSTRAINT c_httptestitem_1 FOREIGN KEY (httptestid) REFERENCES httptest (httptestid) ON DELETE CASCADE;
ALTER TABLE httptestitem ADD CONSTRAINT c_httptestitem_2 FOREIGN KEY (itemid) REFERENCES items (itemid) ON DELETE CASCADE;
