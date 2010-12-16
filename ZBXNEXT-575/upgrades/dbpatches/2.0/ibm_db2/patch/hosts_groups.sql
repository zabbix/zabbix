ALTER TABLE hosts_groups ALTER COLUMN hostgroupid SET WITH DEFAULT NULL
/
REORG TABLE hosts_groups
/
ALTER TABLE hosts_groups ALTER COLUMN hostid SET WITH DEFAULT NULL
/
REORG TABLE hosts_groups
/
ALTER TABLE hosts_groups ALTER COLUMN groupid SET WITH DEFAULT NULL
/
REORG TABLE hosts_groups
/
DROP INDEX hosts_groups_1
/
DELETE FROM hosts_groups WHERE NOT hostid IN (SELECT hostid FROM hosts)
/
DELETE FROM hosts_groups WHERE NOT groupid IN (SELECT groupid FROM groups)
/
CREATE UNIQUE INDEX hosts_groups_1 ON hosts_groups (hostid,groupid)
/
ALTER TABLE hosts_groups ADD CONSTRAINT c_hosts_groups_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE
/
REORG TABLE hosts_groups
/
ALTER TABLE hosts_groups ADD CONSTRAINT c_hosts_groups_2 FOREIGN KEY (groupid) REFERENCES groups (groupid) ON DELETE CASCADE
/
REORG TABLE hosts_groups
/
