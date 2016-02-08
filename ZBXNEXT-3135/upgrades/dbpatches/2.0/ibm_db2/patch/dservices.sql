ALTER TABLE dservices ALTER COLUMN dserviceid SET WITH DEFAULT NULL
/
REORG TABLE dservices
/
ALTER TABLE dservices ALTER COLUMN dhostid SET WITH DEFAULT NULL
/
REORG TABLE dservices
/
ALTER TABLE dservices ALTER COLUMN dcheckid SET WITH DEFAULT NULL
/
REORG TABLE dservices
/
ALTER TABLE dservices ALTER COLUMN key_ SET WITH DEFAULT ''
/
REORG TABLE dservices
/
ALTER TABLE dservices ALTER COLUMN value SET WITH DEFAULT ''
/
REORG TABLE dservices
/
ALTER TABLE dservices ADD dns varchar(64) WITH DEFAULT '' NOT NULL
/
REORG TABLE dservices
/
DELETE FROM dservices WHERE NOT dhostid IN (SELECT dhostid FROM dhosts)
/
DELETE FROM dservices WHERE NOT dcheckid IN (SELECT dcheckid FROM dchecks)
/
ALTER TABLE dservices ADD CONSTRAINT c_dservices_1 FOREIGN KEY (dhostid) REFERENCES dhosts (dhostid) ON DELETE CASCADE
/
ALTER TABLE dservices ADD CONSTRAINT c_dservices_2 FOREIGN KEY (dcheckid) REFERENCES dchecks (dcheckid) ON DELETE CASCADE
/
