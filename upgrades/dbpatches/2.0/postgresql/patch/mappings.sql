ALTER TABLE ONLY mappings ALTER mappingid DROP DEFAULT,
			  ALTER valuemapid DROP DEFAULT;
DELETE FROM mappings WHERE NOT EXISTS (SELECT 1 FROM valuemaps WHERE valuemaps.valuemapid=mappings.valuemapid);
ALTER TABLE ONLY mappings ADD CONSTRAINT c_mappings_1 FOREIGN KEY (valuemapid) REFERENCES valuemaps (valuemapid) ON DELETE CASCADE;
