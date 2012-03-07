ALTER TABLE mappings MODIFY mappingid bigint unsigned NOT NULL,
		     MODIFY valuemapid bigint unsigned NOT NULL;
DELETE FROM mappings WHERE NOT valuemapid IN (SELECT valuemapid FROM valuemaps);
ALTER TABLE mappings ADD CONSTRAINT c_mappings_1 FOREIGN KEY (valuemapid) REFERENCES valuemaps (valuemapid) ON DELETE CASCADE;
