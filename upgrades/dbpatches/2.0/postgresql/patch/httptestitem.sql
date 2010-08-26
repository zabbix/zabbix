ALTER TABLE ONLY httptestitem ALTER httptestitemid DROP DEFAULT,
			      ALTER httptestid DROP DEFAULT,
			      ALTER itemid DROP DEFAULT;
DELETE FROM httptestitem WHERE NOT httptestid IN (SELECT httptestid FROM httptest);
DELETE FROM httptestitem WHERE NOT itemid IN (SELECT itemid FROM items);
ALTER TABLE ONLY httptestitem ADD CONSTRAINT c_httptestitem_1 FOREIGN KEY (httptestid) REFERENCES httptest (httptestid) ON DELETE CASCADE;
ALTER TABLE ONLY httptestitem ADD CONSTRAINT c_httptestitem_2 FOREIGN KEY (itemid) REFERENCES items (itemid) ON DELETE CASCADE;
