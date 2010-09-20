ALTER TABLE ONLY dchecks ALTER dcheckid DROP DEFAULT,
			 ALTER druleid DROP DEFAULT,
			 ADD uniq integer DEFAULT '0' NOT NULL;
DELETE FROM dchecks WHERE NOT druleid IN (SELECT druleid FROM drules);
ALTER TABLE ONLY dchecks ADD CONSTRAINT c_dchecks_1 FOREIGN KEY (druleid) REFERENCES drules (druleid) ON DELETE CASCADE;
UPDATE dchecks SET uniq=1 WHERE dcheckid IN (SELECT unique_dcheckid FROM drules);
ALTER TABLE ONLY drules ALTER druleid DROP DEFAULT,
			ALTER proxy_hostid DROP DEFAULT,
			ALTER proxy_hostid DROP NOT NULL,
			DROP unique_dcheckid;
UPDATE drules SET proxy_hostid=NULL WHERE NOT proxy_hostid IN (SELECT hostid FROM hosts);
ALTER TABLE ONLY drules ADD CONSTRAINT c_drules_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid);
