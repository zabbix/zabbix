ALTER TABLE hosts_profiles_ext MODIFY hostid bigint unsigned NOT NULL;
DELETE FROM hosts_profiles_ext WHERE NOT hostid IN (SELECT hostid FROM hosts);
ALTER TABLE hosts_profiles_ext ADD CONSTRAINT c_hosts_profiles_ext_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
