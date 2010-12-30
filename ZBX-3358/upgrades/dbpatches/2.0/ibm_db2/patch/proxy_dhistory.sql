DELETE FROM proxy_dhistory WHERE NOT druleid IN (SELECT druleid FROM drules);
DELETE FROM proxy_dhistory WHERE NOT dcheckid IN (SELECT dcheckid FROM dchecks);
ALTER TABLE proxy_dhistory ALTER COLUMN druleid SET WITH DEFAULT NULL;
REORG TABLE proxy_dhistory;
ALTER TABLE proxy_dhistory ALTER COLUMN dcheckid SET WITH DEFAULT NULL;
REORG TABLE proxy_dhistory;
