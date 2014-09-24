ALTER TABLE ONLY services_times ALTER timeid DROP DEFAULT,
				ALTER serviceid DROP DEFAULT;
DELETE FROM services_times WHERE NOT EXISTS (SELECT 1 FROM services WHERE services.serviceid=services_times.serviceid);
ALTER TABLE ONLY services_times ADD CONSTRAINT c_services_times_1 FOREIGN KEY (serviceid) REFERENCES services (serviceid) ON DELETE CASCADE;
