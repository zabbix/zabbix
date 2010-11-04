ALTER TABLE hosts_profiles_ext ALTER COLUMN hostid SET WITH DEFAULT NULL;
REORG TABLE hosts_profiles_ext;
DELETE FROM hosts_profiles_ext WHERE NOT hostid IN (SELECT hostid FROM hosts);
ALTER TABLE hosts_profiles_ext ADD CONSTRAINT c_hosts_profiles_ext_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
REORG TABLE hosts_profiles_ext;
