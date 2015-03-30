ALTER TABLE dservices MODIFY dserviceid bigint unsigned NOT NULL,
		      MODIFY dhostid bigint unsigned NOT NULL,
		      MODIFY dcheckid bigint unsigned NOT NULL,
		      MODIFY key_ varchar(255) DEFAULT '' NOT NULL,
		      MODIFY value varchar(255) DEFAULT '' NOT NULL,
		      ADD dns varchar(64) DEFAULT '' NOT NULL;
DELETE FROM dservices WHERE NOT dhostid IN (SELECT dhostid FROM dhosts);
DELETE FROM dservices WHERE NOT dcheckid IN (SELECT dcheckid FROM dchecks);
ALTER TABLE dservices ADD CONSTRAINT c_dservices_1 FOREIGN KEY (dhostid) REFERENCES dhosts (dhostid) ON DELETE CASCADE;
ALTER TABLE dservices ADD CONSTRAINT c_dservices_2 FOREIGN KEY (dcheckid) REFERENCES dchecks (dcheckid) ON DELETE CASCADE;
