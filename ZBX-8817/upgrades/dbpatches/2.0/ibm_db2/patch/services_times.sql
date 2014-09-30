ALTER TABLE services_times ALTER COLUMN timeid SET WITH DEFAULT NULL
/
REORG TABLE services_times
/
ALTER TABLE services_times ALTER COLUMN serviceid SET WITH DEFAULT NULL
/
REORG TABLE services_times
/
DELETE FROM services_times WHERE NOT serviceid IN (SELECT serviceid FROM services)
/
ALTER TABLE services_times ADD CONSTRAINT c_services_times_1 FOREIGN KEY (serviceid) REFERENCES services (serviceid) ON DELETE CASCADE
/
