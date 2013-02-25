ALTER TABLE service_alarms MODIFY servicealarmid DEFAULT NULL;
ALTER TABLE service_alarms MODIFY serviceid DEFAULT NULL;
DELETE FROM service_alarms WHERE NOT serviceid IN (SELECT serviceid FROM services);
ALTER TABLE service_alarms ADD CONSTRAINT c_service_alarms_1 FOREIGN KEY (serviceid) REFERENCES services (serviceid) ON DELETE CASCADE;
