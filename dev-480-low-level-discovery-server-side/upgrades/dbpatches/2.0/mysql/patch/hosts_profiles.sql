ALTER TABLE hosts_profiles MODIFY hostid bigint unsigned NOT NULL;
DELETE FROM hosts_profiles WHERE NOT hostid IN (SELECT hostid FROM hosts);
ALTER TABLE hosts_profiles ADD CONSTRAINT c_hosts_profiles_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
