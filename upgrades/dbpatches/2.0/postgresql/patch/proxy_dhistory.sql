DELETE FROM proxy_dhistory WHERE NOT EXISTS (SELECT 1 FROM drules WHERE drules.druleid=proxy_dhistory.druleid);
DELETE FROM proxy_dhistory WHERE NOT EXISTS (SELECT 1 FROM dchecks WHERE dchecks.dcheckid=proxy_dhistory.dcheckid);
ALTER TABLE ONLY proxy_dhistory ALTER druleid DROP DEFAULT;
ALTER TABLE ONLY proxy_dhistory ALTER dcheckid DROP DEFAULT;
