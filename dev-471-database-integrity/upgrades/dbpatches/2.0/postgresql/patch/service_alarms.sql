ALTER TABLE ONLY service_alarms ALTER servicealarmid DROP DEFAULT,
				ALTER serviceid DROP DEFAULT;
DELETE FROM service_alarms WHERE NOT serviceid IN (SELECT serviceid FROM services);
ALTER TABLE ONLY service_alarms ADD CONSTRAINT c_service_alarms_1 FOREIGN KEY (serviceid) REFERENCES services (serviceid) ON DELETE CASCADE;
