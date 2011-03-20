DELETE FROM proxy_dhistory WHERE druleid NOT IN (SELECT druleid FROM drules)
/
DELETE FROM proxy_dhistory WHERE dcheckid NOT IN (SELECT dcheckid FROM dchecks)
/
ALTER TABLE proxy_dhistory ALTER COLUMN druleid SET WITH DEFAULT NULL
/
REORG TABLE proxy_dhistory
/
ALTER TABLE proxy_dhistory ALTER COLUMN dcheckid SET WITH DEFAULT NULL
/
REORG TABLE proxy_dhistory
/
ALTER TABLE proxy_dhistory ADD dns varchar(64) WITH DEFAULT '' NOT NULL
/
REORG TABLE proxy_dhistory
/
