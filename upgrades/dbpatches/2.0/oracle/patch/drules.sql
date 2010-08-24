CREATE TABLE druleuniq (
	druleid number(20) NOT NULL,
	dcheckid number(20) NOT NULL,
	PRIMARY KEY (druleid)
);
ALTER TABLE druleuniq ADD CONSTRAINT c_druleuniq_1 FOREIGN KEY (druleid) REFERENCES drules (druleid) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE druleuniq ADD CONSTRAINT c_druleuniq_2 FOREIGN KEY (dcheckid) REFERENCES dchecks (dcheckid) ON UPDATE CASCADE ON DELETE CASCADE;

INSERT INTO druleuniq (druleuniqid,druleid,dcheckid)
	SELECT druleid,druleid,unique_dcheckid FROM drules WHERE unique_dcheckid IN (SELECT dcheckid FROM dchecks);

ALTER TABLE drules MODIFY druleid DEFAULT NULL;
ALTER TABLE drules MODIFY proxy_hostid DEFAULT NULL;
ALTER TABLE drules MODIFY proxy_hostid NULL;
ALTER TABLE drules DROP unique_dcheckid;
UPDATE drules SET proxy_hostid=NULL WHERE NOT proxy_hostid IN (SELECT hostid FROM hosts);
ALTER TABLE drules ADD CONSTRAINT c_drules_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid) ON UPDATE CASCADE;
