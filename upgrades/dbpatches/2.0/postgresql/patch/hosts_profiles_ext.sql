ALTER TABLE ONLY hosts_profiles_ext ALTER hostid DROP DEFAULT;
DELETE FROM hosts_profiles_ext WHERE NOT hostid IN (SELECT hostid FROM hosts);
ALTER TABLE ONLY hosts_profiles_ext ADD CONSTRAINT c_hosts_profiles_ext_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
