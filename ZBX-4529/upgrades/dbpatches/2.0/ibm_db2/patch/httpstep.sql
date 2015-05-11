ALTER TABLE httpstep ALTER COLUMN httpstepid SET WITH DEFAULT NULL
/
REORG TABLE httpstep
/
ALTER TABLE httpstep ALTER COLUMN httptestid SET WITH DEFAULT NULL
/
REORG TABLE httpstep
/
DELETE FROM httpstep WHERE NOT httptestid IN (SELECT httptestid FROM httptest)
/
ALTER TABLE httpstep ADD CONSTRAINT c_httpstep_1 FOREIGN KEY (httptestid) REFERENCES httptest (httptestid) ON DELETE CASCADE
/
