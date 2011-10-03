DELETE FROM proxy_dhistory WHERE druleid NOT IN (SELECT druleid FROM drules)
/
ALTER TABLE proxy_dhistory ALTER COLUMN druleid SET WITH DEFAULT NULL
/
REORG TABLE proxy_dhistory
/
ALTER TABLE proxy_dhistory ALTER COLUMN dcheckid DROP NOT NULL
/
ALTER TABLE proxy_dhistory ALTER COLUMN dcheckid SET WITH DEFAULT NULL
/
REORG TABLE proxy_dhistory
/
ALTER TABLE proxy_dhistory ADD dns varchar(64) WITH DEFAULT '' NOT NULL
/
REORG TABLE proxy_dhistory
/
UPDATE proxy_dhistory SET dcheckid=NULL WHERE NOT dcheckid IN (SELECT dcheckid FROM dchecks)
/
