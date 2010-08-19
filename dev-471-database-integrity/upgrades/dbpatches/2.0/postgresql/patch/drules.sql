ALTER TABLE ONLY drules ALTER druleid DROP DEFAULT,
			ALTER proxy_hostid DROP DEFAULT,
			ALTER proxy_hostid DROP NOT NULL,
			ALTER unique_dcheckid DROP DEFAULT,
			ALTER unique_dcheckid DROP NOT NULL;
UPDATE drules SET proxy_hostid=NULL WHERE NOT proxy_hostid IN (SELECT hostid FROM hosts);
UPDATE drules SET unique_dcheckid=NULL WHERE NOT unique_dcheckid IN (SELECT dcheckid FROM dchecks);
ALTER TABLE ONLY drules ADD CONSTRAINT c_drules_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid);
ALTER TABLE ONLY drules ADD CONSTRAINT c_drules_2 FOREIGN KEY (unique_dcheckid) REFERENCES dchecks (dcheckid);
