ALTER TABLE ONLY maintenances_windows ALTER maintenance_timeperiodid DROP DEFAULT,
				      ALTER maintenanceid DROP DEFAULT,
				      ALTER timeperiodid DROP DEFAULT;
DROP INDEX maintenances_windows_1;
DELETE FROM maintenances_windows WHERE NOT EXISTS (SELECT 1 FROM maintenances WHERE maintenances.maintenanceid=maintenances_windows.maintenanceid);
DELETE FROM maintenances_windows WHERE NOT EXISTS (SELECT 1 FROM timeperiods WHERE timeperiods.timeperiodid=maintenances_windows.timeperiodid);
CREATE UNIQUE INDEX maintenances_windows_1 ON maintenances_windows (maintenanceid,timeperiodid);
ALTER TABLE ONLY maintenances_windows ADD CONSTRAINT c_maintenances_windows_1 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid) ON DELETE CASCADE;
ALTER TABLE ONLY maintenances_windows ADD CONSTRAINT c_maintenances_windows_2 FOREIGN KEY (timeperiodid) REFERENCES timeperiods (timeperiodid) ON DELETE CASCADE;
