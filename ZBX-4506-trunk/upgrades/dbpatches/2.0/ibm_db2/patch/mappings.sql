ALTER TABLE mappings ALTER COLUMN mappingid SET WITH DEFAULT NULL
/
REORG TABLE mappings
/
ALTER TABLE mappings ALTER COLUMN valuemapid SET WITH DEFAULT NULL
/
REORG TABLE mappings
/
DELETE FROM mappings WHERE NOT valuemapid IN (SELECT valuemapid FROM valuemaps)
/
ALTER TABLE mappings ADD CONSTRAINT c_mappings_1 FOREIGN KEY (valuemapid) REFERENCES valuemaps (valuemapid) ON DELETE CASCADE
/
