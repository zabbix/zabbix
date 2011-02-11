DELETE FROM proxy_dhistory WHERE NOT druleid IN (SELECT druleid FROM drules);
DELETE FROM proxy_dhistory WHERE NOT dcheckid IN (SELECT dcheckid FROM dchecks);
ALTER TABLE proxy_dhistory MODIFY druleid bigint unsigned NOT NULL;
ALTER TABLE proxy_dhistory MODIFY dcheckid bigint unsigned NOT NULL;
ALTER TABLE proxy_dhistory ADD dns varchar(64) DEFAULT '' NOT NULL;
