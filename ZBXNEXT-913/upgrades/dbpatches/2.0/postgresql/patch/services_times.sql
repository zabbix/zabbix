ALTER TABLE ONLY services_times
	ALTER timeid DROP DEFAULT,
	ALTER serviceid DROP DEFAULT;
DELETE FROM services_times WHERE NOT EXISTS (SELECT 1 FROM services WHERE services.serviceid=services_times.serviceid);
ALTER TABLE ONLY services_times ADD CONSTRAINT c_services_times_1 FOREIGN KEY (serviceid) REFERENCES services (serviceid) ON DELETE CASCADE;

UPDATE services_times SET
	ts_from = extract('dow' FROM to_timestamp(ts_from)) * 86400 +
		extract('hour' FROM to_timestamp(ts_from)) * 3600 +
		extract('minute' FROM to_timestamp(ts_from)) * 60,
	ts_to = extract('dow' FROM to_timestamp(ts_to)) * 86400 +
		extract('hour' FROM to_timestamp(ts_to)) * 3600 +
		extract('minute' FROM to_timestamp(ts_to)) * 60
	WHERE TYPE IN (0,1);	-- SERVICE_TIME_TYPE_UPTIME, SERVICE_TIME_TYPE_DOWNTIME
