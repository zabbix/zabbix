ALTER TABLE dchecks MODIFY dcheckid bigint unsigned NOT NULL,
		    MODIFY druleid bigint unsigned NOT NULL;
DELETE FROM dchecks WHERE NOT druleid IN (SELECT druleid FROM drules);
ALTER TABLE dchecks ADD CONSTRAINT c_dchecks_1 FOREIGN KEY (druleid) REFERENCES drules (druleid) ON UPDATE CASCADE ON DELETE CASCADE;
