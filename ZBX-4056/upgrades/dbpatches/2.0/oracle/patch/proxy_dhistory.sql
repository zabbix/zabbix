DELETE FROM proxy_dhistory WHERE NOT druleid IN (SELECT druleid FROM drules);
ALTER TABLE proxy_dhistory MODIFY druleid DEFAULT NULL;
ALTER TABLE proxy_dhistory MODIFY dcheckid NULL;
ALTER TABLE proxy_dhistory MODIFY dcheckid DEFAULT NULL;
ALTER TABLE proxy_dhistory ADD dns nvarchar2(64) DEFAULT '';
UPDATE proxy_dhistory SET dcheckid=NULL WHERE NOT dcheckid IN (SELECT dcheckid FROM dchecks);
