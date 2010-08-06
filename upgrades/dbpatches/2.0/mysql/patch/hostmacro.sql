DROP INDEX hostmacro_1 ON hostmacro;
ALTER TABLE hostmacro MODIFY hostid bigint unsigned NOT NULL;
DELETE FROM hostmacro WHERE NOT hostid IN (SELECT hostid FROM hosts);
CREATE UNIQUE INDEX hostmacro_1 ON hostmacro (hostid,macro);
ALTER TABLE hostmacro ADD CONSTRAINT c_hostmacro_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
