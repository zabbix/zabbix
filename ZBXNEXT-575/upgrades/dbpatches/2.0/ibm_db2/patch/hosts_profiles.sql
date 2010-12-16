ALTER TABLE hosts_profiles ALTER COLUMN hostid SET WITH DEFAULT NULL
/
REORG TABLE hosts_profiles
/
DELETE FROM hosts_profiles WHERE NOT hostid IN (SELECT hostid FROM hosts)
/
ALTER TABLE hosts_profiles ADD CONSTRAINT c_hosts_profiles_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE
/
REORG TABLE hosts_profiles
/
