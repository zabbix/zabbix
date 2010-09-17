ALTER TABLE ONLY services_times ALTER timeid DROP DEFAULT,
				ALTER serviceid DROP DEFAULT;
DELETE FROM services_times WHERE NOT serviceid IN (SELECT serviceid FROM services);
ALTER TABLE ONLY services_times ADD CONSTRAINT c_services_times_1 FOREIGN KEY (serviceid) REFERENCES services (serviceid) ON DELETE CASCADE;
