CREATE TABLE druleuniq (
	druleid bigint unsigned NOT NULL,
	dcheckid bigint unsigned NOT NULL,
	PRIMARY KEY (druleid)
) ENGINE=InnoDB;
ALTER TABLE druleuniq ADD CONSTRAINT c_druleuniq_1 FOREIGN KEY (druleid) REFERENCES drules (druleid) ON DELETE CASCADE;
ALTER TABLE druleuniq ADD CONSTRAINT c_druleuniq_2 FOREIGN KEY (dcheckid) REFERENCES dchecks (dcheckid) ON DELETE CASCADE;

INSERT INTO druleuniq (druleid,dcheckid)
	SELECT druleid,unique_dcheckid FROM drules WHERE unique_dcheckid IN (SELECT dcheckid FROM dchecks);

ALTER TABLE drules MODIFY druleid bigint unsigned NOT NULL,
		   MODIFY proxy_hostid bigint unsigned NULL,
		   DROP unique_dcheckid;
UPDATE drules SET proxy_hostid=NULL WHERE NOT proxy_hostid IN (SELECT hostid FROM hosts);
ALTER TABLE drules ADD CONSTRAINT c_drules_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid);
