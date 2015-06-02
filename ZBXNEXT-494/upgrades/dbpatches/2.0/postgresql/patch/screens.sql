ALTER TABLE ONLY screens ALTER screenid DROP DEFAULT,
			 ALTER name DROP DEFAULT,
			 ADD templateid bigint NULL;
ALTER TABLE ONLY screens ADD CONSTRAINT c_screens_1 FOREIGN KEY (templateid) REFERENCES hosts (hostid) ON DELETE CASCADE;
