ALTER TABLE ONLY httptestitem ALTER httptestitemid DROP DEFAULT,
			      ALTER httptestid DROP DEFAULT,
			      ALTER itemid DROP DEFAULT;
DELETE FROM httptestitem WHERE NOT EXISTS (SELECT 1 FROM httptest WHERE httptest.httptestid=httptestitem.httptestid);
DELETE FROM httptestitem WHERE NOT EXISTS (SELECT 1 FROM items WHERE items.itemid=httptestitem.itemid);
ALTER TABLE ONLY httptestitem ADD CONSTRAINT c_httptestitem_1 FOREIGN KEY (httptestid) REFERENCES httptest (httptestid) ON DELETE CASCADE;
ALTER TABLE ONLY httptestitem ADD CONSTRAINT c_httptestitem_2 FOREIGN KEY (itemid) REFERENCES items (itemid) ON DELETE CASCADE;
