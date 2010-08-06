DELETE FROM dchecks WHERE NOT druleid IN (SELECT druleid FROM drules);
ALTER TABLE dchecks MODIFY druleid DEFAULT NULL;
ALTER TABLE dchecks ADD CONSTRAINT c_dchecks_1 FOREIGN KEY (druleid) REFERENCES drules (druleid) ON DELETE CASCADE;
