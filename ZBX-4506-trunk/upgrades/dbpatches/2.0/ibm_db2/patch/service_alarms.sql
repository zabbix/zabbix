ALTER TABLE service_alarms ALTER COLUMN servicealarmid SET WITH DEFAULT NULL
/
REORG TABLE service_alarms
/
ALTER TABLE service_alarms ALTER COLUMN serviceid SET WITH DEFAULT NULL
/
REORG TABLE service_alarms
/
DELETE FROM service_alarms WHERE NOT serviceid IN (SELECT serviceid FROM services)
/
ALTER TABLE service_alarms ADD CONSTRAINT c_service_alarms_1 FOREIGN KEY (serviceid) REFERENCES services (serviceid) ON DELETE CASCADE
/
