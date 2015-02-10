ALTER TABLE httpstep
	MODIFY httpstepid bigint unsigned NOT NULL,
	MODIFY httptestid bigint unsigned NOT NULL,
	MODIFY posts text NOT NULL;
DELETE FROM httpstep WHERE NOT httptestid IN (SELECT httptestid FROM httptest);
ALTER TABLE httpstep ADD CONSTRAINT c_httpstep_1 FOREIGN KEY (httptestid) REFERENCES httptest (httptestid) ON DELETE CASCADE;
