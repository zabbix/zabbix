DELETE FROM proxy_dhistory WHERE NOT druleid IN (SELECT druleid FROM drules);
ALTER TABLE proxy_dhistory MODIFY druleid bigint unsigned NOT NULL;
ALTER TABLE proxy_dhistory MODIFY dcheckid bigint unsigned NULL;
ALTER TABLE proxy_dhistory ADD dns varchar(64) DEFAULT '' NOT NULL;
UPDATE proxy_dhistory SET dcheckid=NULL WHERE NOT dcheckid IN (SELECT dcheckid FROM dchecks);
