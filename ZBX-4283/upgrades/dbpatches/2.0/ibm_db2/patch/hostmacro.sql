ALTER TABLE hostmacro ALTER COLUMN hostmacroid SET WITH DEFAULT NULL
/
REORG TABLE hostmacro
/
ALTER TABLE hostmacro ALTER COLUMN hostid SET WITH DEFAULT NULL
/
REORG TABLE hostmacro
/
DROP INDEX hostmacro_1
/
DELETE FROM hostmacro WHERE NOT hostid IN (SELECT hostid FROM hosts)
/
CREATE UNIQUE INDEX hostmacro_1 ON hostmacro (hostid,macro)
/
ALTER TABLE hostmacro ADD CONSTRAINT c_hostmacro_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE
/
