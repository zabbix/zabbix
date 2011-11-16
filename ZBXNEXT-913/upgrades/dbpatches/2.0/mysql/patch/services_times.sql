ALTER TABLE services_times
	MODIFY timeid bigint unsigned NOT NULL,
	MODIFY serviceid bigint unsigned NOT NULL;
DELETE FROM services_times WHERE NOT serviceid IN (SELECT serviceid FROM services);
ALTER TABLE services_times ADD CONSTRAINT c_services_times_1 FOREIGN KEY (serviceid) REFERENCES services (serviceid) ON DELETE CASCADE;

UPDATE services_times SET
	ts_from = (dayofweek(from_unixtime(ts_from)) - 1) * 86400 +
		hour(from_unixtime(ts_from)) * 3600 +
		minute(from_unixtime(ts_from)) * 60,
	ts_to = (dayofweek(from_unixtime(ts_to)) - 1) * 86400 +
		hour(from_unixtime(ts_to)) * 3600 +
		minute(from_unixtime(ts_to)) * 60
	WHERE TYPE IN (0,1);	-- SERVICE_TIME_TYPE_UPTIME, SERVICE_TIME_TYPE_DOWNTIME
