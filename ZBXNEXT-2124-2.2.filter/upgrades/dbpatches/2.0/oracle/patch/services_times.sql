ALTER TABLE services_times MODIFY timeid DEFAULT NULL;
ALTER TABLE services_times MODIFY serviceid DEFAULT NULL;
DELETE FROM services_times WHERE NOT serviceid IN (SELECT serviceid FROM services);
ALTER TABLE services_times ADD CONSTRAINT c_services_times_1 FOREIGN KEY (serviceid) REFERENCES services (serviceid) ON DELETE CASCADE;
