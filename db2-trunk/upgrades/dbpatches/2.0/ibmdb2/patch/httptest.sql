ALTER TABLE httptest ALTER COLUMN httptestid SET WITH DEFAULT NULL;
REORG TABLE httptest;
ALTER TABLE httptest ALTER COLUMN applicationid SET WITH DEFAULT NULL;
REORG TABLE httptest;
DELETE FROM httptest WHERE NOT applicationid IN (SELECT applicationid FROM applications);
ALTER TABLE httptest ADD CONSTRAINT c_httptest_1 FOREIGN KEY (applicationid) REFERENCES applications (applicationid) ON DELETE CASCADE;
REORG TABLE httptest;
