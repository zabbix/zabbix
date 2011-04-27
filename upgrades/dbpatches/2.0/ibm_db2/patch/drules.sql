ALTER TABLE dchecks ALTER COLUMN dcheckid SET WITH DEFAULT NULL
/
REORG TABLE dchecks
/
ALTER TABLE dchecks ALTER COLUMN druleid SET WITH DEFAULT NULL
/
REORG TABLE dchecks
/
ALTER TABLE dchecks ALTER COLUMN key_ SET WITH DEFAULT ''
/
REORG TABLE dchecks
/
ALTER TABLE dchecks ALTER COLUMN snmp_community SET WITH DEFAULT ''
/
REORG TABLE dchecks
/
ALTER TABLE dchecks ADD uniq integer DEFAULT '0' NOT NULL
/
REORG TABLE dchecks
/
DELETE FROM dchecks WHERE NOT druleid IN (SELECT druleid FROM drules)
/
ALTER TABLE dchecks ADD CONSTRAINT c_dchecks_1 FOREIGN KEY (druleid) REFERENCES drules (druleid) ON DELETE CASCADE
/
UPDATE dchecks SET uniq=1 WHERE dcheckid IN (SELECT unique_dcheckid FROM drules)
/
ALTER TABLE drules ALTER COLUMN druleid SET WITH DEFAULT NULL
/
REORG TABLE drules
/
ALTER TABLE drules ALTER COLUMN proxy_hostid SET WITH DEFAULT NULL
/
REORG TABLE drules
/
ALTER TABLE drules ALTER COLUMN proxy_hostid DROP NOT NULL
/
REORG TABLE drules
/
ALTER TABLE drules ALTER COLUMN delay SET WITH DEFAULT '3600'
/
REORG TABLE drules
/
ALTER TABLE drules DROP COLUMN unique_dcheckid
/
REORG TABLE drules
/
UPDATE drules SET proxy_hostid=NULL WHERE NOT proxy_hostid IN (SELECT hostid FROM hosts)
/
ALTER TABLE drules ADD CONSTRAINT c_drules_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid)
/
