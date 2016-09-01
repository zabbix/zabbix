ALTER TABLE maintenances_windows ALTER COLUMN maintenance_timeperiodid SET WITH DEFAULT NULL
/
REORG TABLE maintenances_windows
/
ALTER TABLE maintenances_windows ALTER COLUMN maintenanceid SET WITH DEFAULT NULL
/
REORG TABLE maintenances_windows
/
ALTER TABLE maintenances_windows ALTER COLUMN timeperiodid SET WITH DEFAULT NULL
/
REORG TABLE maintenances_windows
/
DROP INDEX maintenances_windows_1
/
DELETE FROM maintenances_windows WHERE maintenanceid NOT IN (SELECT maintenanceid FROM maintenances)
/
DELETE FROM maintenances_windows WHERE timeperiodid NOT IN (SELECT timeperiodid FROM timeperiods)
/
CREATE UNIQUE INDEX maintenances_windows_1 ON maintenances_windows (maintenanceid,timeperiodid)
/
ALTER TABLE maintenances_windows ADD CONSTRAINT c_maintenances_windows_1 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid) ON DELETE CASCADE
/
ALTER TABLE maintenances_windows ADD CONSTRAINT c_maintenances_windows_2 FOREIGN KEY (timeperiodid) REFERENCES timeperiods (timeperiodid) ON DELETE CASCADE
/
