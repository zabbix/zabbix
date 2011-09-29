DELETE FROM proxy_dhistory WHERE NOT EXISTS (SELECT 1 FROM drules WHERE drules.druleid=proxy_dhistory.druleid);
ALTER TABLE ONLY proxy_dhistory ALTER druleid DROP DEFAULT;
ALTER TABLE ONLY proxy_dhistory ALTER dcheckid DROP NOT NULL;
ALTER TABLE ONLY proxy_dhistory ALTER dcheckid DROP DEFAULT;
ALTER TABLE ONLY proxy_dhistory ADD dns varchar(64) DEFAULT '' NOT NULL;
UPDATE proxy_dhistory SET dcheckid=NULL WHERE NOT EXISTS (SELECT 1 FROM dchecks WHERE dchecks.dcheckid=proxy_dhistory.dcheckid);
