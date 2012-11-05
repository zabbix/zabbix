ALTER TABLE httptest
	MODIFY httptestid bigint unsigned NOT NULL,
	MODIFY applicationid bigint unsigned NOT NULL,
	MODIFY macros text NOT NULL,
	DROP COLUMN lastcheck,
	DROP COLUMN curstate,
	DROP COLUMN curstep,
	DROP COLUMN lastfailedstep,
	DROP COLUMN time,
	DROP COLUMN error;
DELETE FROM httptest WHERE applicationid NOT IN (SELECT applicationid FROM applications);
ALTER TABLE httptest ADD CONSTRAINT c_httptest_1 FOREIGN KEY (applicationid) REFERENCES applications (applicationid) ON DELETE CASCADE;
