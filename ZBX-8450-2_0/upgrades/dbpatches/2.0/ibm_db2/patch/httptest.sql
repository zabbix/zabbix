ALTER TABLE httptest ALTER COLUMN httptestid SET WITH DEFAULT NULL
/
REORG TABLE httptest
/
ALTER TABLE httptest ALTER COLUMN applicationid SET WITH DEFAULT NULL
/
REORG TABLE httptest
/
ALTER TABLE httptest DROP COLUMN lastcheck
/
REORG TABLE httptest
/
ALTER TABLE httptest DROP COLUMN curstate
/
REORG TABLE httptest
/
ALTER TABLE httptest DROP COLUMN curstep
/
REORG TABLE httptest
/
ALTER TABLE httptest DROP COLUMN lastfailedstep
/
REORG TABLE httptest
/
ALTER TABLE httptest DROP COLUMN time
/
REORG TABLE httptest
/
ALTER TABLE httptest DROP COLUMN error
/
REORG TABLE httptest
/
DELETE FROM httptest WHERE applicationid NOT IN (SELECT applicationid FROM applications)
/
ALTER TABLE httptest ADD CONSTRAINT c_httptest_1 FOREIGN KEY (applicationid) REFERENCES applications (applicationid) ON DELETE CASCADE
/
