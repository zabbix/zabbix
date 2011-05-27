ALTER TABLE httptest MODIFY httptestid bigint unsigned NOT NULL,
		     MODIFY applicationid bigint unsigned NOT NULL;
DELETE FROM httptest WHERE NOT applicationid IN (SELECT applicationid FROM applications);
ALTER TABLE httptest ADD CONSTRAINT c_httptest_1 FOREIGN KEY (applicationid) REFERENCES applications (applicationid) ON DELETE CASCADE;
