ALTER TABLE mappings MODIFY mappingid DEFAULT NULL;
ALTER TABLE mappings MODIFY valuemapid DEFAULT NULL;
DELETE FROM mappings WHERE NOT valuemapid IN (SELECT valuemapid FROM valuemaps);
ALTER TABLE mappings ADD CONSTRAINT c_mappings_1 FOREIGN KEY (valuemapid) REFERENCES valuemaps (valuemapid) ON DELETE CASCADE;
