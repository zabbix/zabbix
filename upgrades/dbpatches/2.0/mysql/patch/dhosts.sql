DELETE FROM dhosts WHERE NOT druleid IN (SELECT druleid FROM drules);
ALTER TABLE dhosts MODIFY druleid bigint unsigned NOT NULL;
ALTER TABLE dhosts ADD CONSTRAINT c_dhosts_1 FOREIGN KEY (druleid) REFERENCES drules (druleid) ON DELETE CASCADE;
