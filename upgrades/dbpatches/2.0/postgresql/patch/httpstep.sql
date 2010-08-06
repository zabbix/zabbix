DELETE FROM httpstep WHERE NOT httptestid IN (SELECT httptestid FROM httptest);
ALTER TABLE ONLY httpstep ALTER httptestid DROP DEFAULT;
ALTER TABLE ONLY httpstep ADD CONSTRAINT c_httpstep_1 FOREIGN KEY (httptestid) REFERENCES httptest (httptestid) ON DELETE CASCADE;
