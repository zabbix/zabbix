ALTER TABLE ONLY httpstep ALTER httpstepid DROP DEFAULT,
			  ALTER httptestid DROP DEFAULT;
DELETE FROM httpstep WHERE NOT EXISTS (SELECT 1 FROM httptest WHERE httptest.httptestid=httpstep.httptestid);
ALTER TABLE ONLY httpstep ADD CONSTRAINT c_httpstep_1 FOREIGN KEY (httptestid) REFERENCES httptest (httptestid) ON DELETE CASCADE;
