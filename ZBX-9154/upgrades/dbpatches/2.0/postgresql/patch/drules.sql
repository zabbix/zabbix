ALTER TABLE ONLY dchecks ALTER dcheckid DROP DEFAULT,
			 ALTER druleid DROP DEFAULT,
			 ALTER key_ SET DEFAULT '',
			 ALTER snmp_community SET DEFAULT '',
			 ADD uniq integer DEFAULT '0' NOT NULL;
DELETE FROM dchecks WHERE NOT EXISTS (SELECT 1 FROM drules WHERE drules.druleid=dchecks.druleid);
ALTER TABLE ONLY dchecks ADD CONSTRAINT c_dchecks_1 FOREIGN KEY (druleid) REFERENCES drules (druleid) ON DELETE CASCADE;
UPDATE dchecks SET uniq=1 WHERE EXISTS (SELECT 1 FROM drules WHERE drules.unique_dcheckid=dchecks.dcheckid);
ALTER TABLE ONLY drules ALTER druleid DROP DEFAULT,
			ALTER proxy_hostid DROP DEFAULT,
			ALTER proxy_hostid DROP NOT NULL,
			ALTER delay SET DEFAULT '3600',
			DROP unique_dcheckid;
UPDATE drules SET proxy_hostid=NULL WHERE NOT EXISTS (SELECT 1 FROM hosts WHERE hosts.hostid=drules.proxy_hostid);
ALTER TABLE ONLY drules ADD CONSTRAINT c_drules_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid);
