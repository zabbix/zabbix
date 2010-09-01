ALTER TABLE ONLY mappings ALTER mappingid DROP DEFAULT,
			  ALTER valuemapid DROP DEFAULT;
DELETE FROM mappings WHERE NOT valuemapid IN (SELECT valuemapid FROM valuemaps);
ALTER TABLE ONLY mappings ADD CONSTRAINT c_mappings_1 FOREIGN KEY (valuemapid) REFERENCES valuemaps (valuemapid) ON DELETE CASCADE;
