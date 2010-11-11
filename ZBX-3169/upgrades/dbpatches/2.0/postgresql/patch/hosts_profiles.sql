ALTER TABLE ONLY hosts_profiles ALTER hostid DROP DEFAULT;
DELETE FROM hosts_profiles WHERE NOT EXISTS (SELECT 1 FROM hosts WHERE hosts.hostid=hosts_profiles.hostid);
ALTER TABLE ONLY hosts_profiles ADD CONSTRAINT c_hosts_profiles_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
