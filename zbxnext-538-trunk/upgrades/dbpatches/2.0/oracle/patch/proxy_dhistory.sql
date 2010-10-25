DELETE FROM proxy_dhistory WHERE NOT druleid IN (SELECT druleid FROM drules);
DELETE FROM proxy_dhistory WHERE NOT dcheckid IN (SELECT dcheckid FROM dchecks);
ALTER TABLE proxy_dhistory MODIFY druleid DEFAULT NULL;
ALTER TABLE proxy_dhistory MODIFY dcheckid DEFAULT NULL;
