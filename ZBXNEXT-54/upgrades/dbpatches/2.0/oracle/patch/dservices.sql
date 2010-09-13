ALTER TABLE dservices MODIFY dserviceid DEFAULT NULL;
ALTER TABLE dservices MODIFY dhostid DEFAULT NULL;
ALTER TABLE dservices MODIFY dcheckid DEFAULT NULL;
DELETE FROM dservices WHERE NOT dhostid IN (SELECT dhostid FROM dhosts);
DELETE FROM dservices WHERE NOT dcheckid IN (SELECT dcheckid FROM dchecks);
ALTER TABLE dservices ADD CONSTRAINT c_dservices_1 FOREIGN KEY (dhostid) REFERENCES dhosts (dhostid) ON DELETE CASCADE;
ALTER TABLE dservices ADD CONSTRAINT c_dservices_2 FOREIGN KEY (dcheckid) REFERENCES dchecks (dcheckid) ON DELETE CASCADE;
