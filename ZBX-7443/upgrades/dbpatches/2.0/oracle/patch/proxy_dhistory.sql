DELETE FROM proxy_dhistory WHERE druleid NOT IN (SELECT druleid FROM drules);
DELETE FROM proxy_dhistory WHERE dcheckid<>0 AND dcheckid NOT IN (SELECT dcheckid FROM dchecks);
ALTER TABLE proxy_dhistory MODIFY druleid DEFAULT NULL;
ALTER TABLE proxy_dhistory MODIFY dcheckid NULL;
ALTER TABLE proxy_dhistory MODIFY dcheckid DEFAULT NULL;
ALTER TABLE proxy_dhistory ADD dns nvarchar2(64) DEFAULT '';
UPDATE proxy_dhistory SET dcheckid=NULL WHERE dcheckid=0;
