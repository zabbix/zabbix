DELETE FROM proxy_dhistory WHERE druleid NOT IN (SELECT druleid FROM drules);
DELETE FROM proxy_dhistory WHERE dcheckid<>0 AND dcheckid NOT IN (SELECT dcheckid FROM dchecks);
ALTER TABLE proxy_dhistory MODIFY druleid bigint unsigned NOT NULL;
ALTER TABLE proxy_dhistory MODIFY dcheckid bigint unsigned NULL;
ALTER TABLE proxy_dhistory ADD dns varchar(64) DEFAULT '' NOT NULL;
UPDATE proxy_dhistory SET dcheckid=NULL WHERE dcheckid=0;
