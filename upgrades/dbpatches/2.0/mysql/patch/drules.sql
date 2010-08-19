ALTER TABLE drules MODIFY druleid bigint unsigned NOT NULL,
		   MODIFY proxy_hostid bigint unsigned NULL,
		   MODIFY unique_dcheckid bigint unsigned NULL;
UPDATE drules SET proxy_hostid=NULL WHERE NOT proxy_hostid IN (SELECT hostid FROM hosts);
UPDATE drules SET unique_dcheckid=NULL WHERE NOT unique_dcheckid IN (SELECT dcheckid FROM dchecks);
ALTER TABLE drules ADD CONSTRAINT c_drules_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid);
ALTER TABLE drules ADD CONSTRAINT c_drules_2 FOREIGN KEY (unique_dcheckid) REFERENCES dchecks (dcheckid);
