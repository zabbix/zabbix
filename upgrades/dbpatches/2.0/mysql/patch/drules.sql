ALTER TABLE dchecks MODIFY dcheckid bigint unsigned NOT NULL,
		    MODIFY druleid bigint unsigned NOT NULL,
		    MODIFY key_ varchar(255) DEFAULT '' NOT NULL,
		    MODIFY snmp_community varchar(255) DEFAULT '' NOT NULL,
		    ADD uniq integer DEFAULT '0' NOT NULL;
DELETE FROM dchecks WHERE NOT druleid IN (SELECT druleid FROM drules);
ALTER TABLE dchecks ADD CONSTRAINT c_dchecks_1 FOREIGN KEY (druleid) REFERENCES drules (druleid) ON DELETE CASCADE;
UPDATE dchecks SET uniq=1 WHERE dcheckid IN (SELECT unique_dcheckid FROM drules);
ALTER TABLE drules MODIFY druleid bigint unsigned NOT NULL,
		   MODIFY proxy_hostid bigint unsigned NULL,
		   MODIFY delay integer DEFAULT '3600' NOT NULL,
		   DROP unique_dcheckid;
UPDATE drules SET proxy_hostid=NULL WHERE NOT proxy_hostid IN (SELECT hostid FROM hosts);
ALTER TABLE drules ADD CONSTRAINT c_drules_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid);
