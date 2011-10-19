ALTER TABLE ONLY hosts_groups ALTER hostgroupid DROP DEFAULT,
			      ALTER hostid DROP DEFAULT,
			      ALTER groupid DROP DEFAULT;
DROP INDEX hosts_groups_1;
DELETE FROM hosts_groups WHERE NOT EXISTS (SELECT 1 FROM hosts WHERE hosts.hostid=hosts_groups.hostid);
DELETE FROM hosts_groups WHERE NOT EXISTS (SELECT 1 FROM groups WHERE groups.groupid=hosts_groups.groupid);
CREATE UNIQUE INDEX hosts_groups_1 ON hosts_groups (hostid,groupid);
ALTER TABLE ONLY hosts_groups ADD CONSTRAINT c_hosts_groups_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
ALTER TABLE ONLY hosts_groups ADD CONSTRAINT c_hosts_groups_2 FOREIGN KEY (groupid) REFERENCES groups (groupid) ON DELETE CASCADE;
