ALTER TABLE httpstep MODIFY httpstepid DEFAULT NULL;
ALTER TABLE httpstep MODIFY httptestid DEFAULT NULL;
DELETE FROM httpstep WHERE NOT httptestid IN (SELECT httptestid FROM httptest);
ALTER TABLE httpstep ADD CONSTRAINT c_httpstep_1 FOREIGN KEY (httptestid) REFERENCES httptest (httptestid) ON DELETE CASCADE;
