ALTER TABLE dhosts MODIFY dhostid bigint unsigned NOT NULL,
		   MODIFY druleid bigint unsigned NOT NULL;
DELETE FROM dhosts WHERE NOT druleid IN (SELECT druleid FROM drules);
ALTER TABLE dhosts ADD CONSTRAINT c_dhosts_1 FOREIGN KEY (druleid) REFERENCES drules (druleid) ON DELETE CASCADE;
