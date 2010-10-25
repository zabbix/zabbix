DELETE FROM proxy_dhistory WHERE NOT druleid IN (SELECT druleid FROM drules);
DELETE FROM proxy_dhistory WHERE NOT dcheckid IN (SELECT dcheckid FROM dchecks);
ALTER TABLE ONLY proxy_dhistory ALTER druleid DROP DEFAULT;
ALTER TABLE ONLY proxy_dhistory ALTER dcheckid DROP DEFAULT;
