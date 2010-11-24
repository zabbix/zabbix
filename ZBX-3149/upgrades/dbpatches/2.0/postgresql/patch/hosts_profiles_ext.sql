ALTER TABLE ONLY hosts_profiles_ext ALTER hostid DROP DEFAULT;
DELETE FROM hosts_profiles_ext WHERE NOT EXISTS (SELECT hostid FROM hosts WHERE hosts.hostid=hosts_profiles_ext.hostid);
ALTER TABLE ONLY hosts_profiles_ext ADD CONSTRAINT c_hosts_profiles_ext_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
