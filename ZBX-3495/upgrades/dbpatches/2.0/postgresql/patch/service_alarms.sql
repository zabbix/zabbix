ALTER TABLE ONLY service_alarms ALTER servicealarmid DROP DEFAULT,
				ALTER serviceid DROP DEFAULT;
DELETE FROM service_alarms WHERE NOT EXISTS (SELECT 1 FROM services WHERE services.serviceid=service_alarms.serviceid);
ALTER TABLE ONLY service_alarms ADD CONSTRAINT c_service_alarms_1 FOREIGN KEY (serviceid) REFERENCES services (serviceid) ON DELETE CASCADE;
