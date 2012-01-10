ALTER TABLE httptest MODIFY httptestid DEFAULT NULL;
ALTER TABLE httptest MODIFY applicationid DEFAULT NULL;
DELETE FROM httptest WHERE NOT applicationid IN (SELECT applicationid FROM applications);
ALTER TABLE httptest ADD CONSTRAINT c_httptest_1 FOREIGN KEY (applicationid) REFERENCES applications (applicationid) ON DELETE CASCADE;
