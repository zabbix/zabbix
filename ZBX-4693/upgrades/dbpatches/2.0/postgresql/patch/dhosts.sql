ALTER TABLE ONLY dhosts ALTER dhostid DROP DEFAULT,
			ALTER druleid DROP DEFAULT;
DELETE FROM dhosts WHERE NOT EXISTS (SELECT 1 FROM drules WHERE drules.druleid=dhosts.druleid);
ALTER TABLE ONLY dhosts ADD CONSTRAINT c_dhosts_1 FOREIGN KEY (druleid) REFERENCES drules (druleid) ON DELETE CASCADE;
