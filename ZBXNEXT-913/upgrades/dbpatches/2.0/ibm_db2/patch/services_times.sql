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

UPDATE services_times SET
	ts_from = (dayofweek(timestamp('1970-01-01-00.00.00') + ts_from seconds + current timezone) - 1) * 86400 +
		hour(timestamp('1970-01-01-00.00.00') + ts_from seconds + current timezone) * 3600 +
		minute(timestamp('1970-01-01-00.00.00') + ts_from seconds + current timezone) * 60,
	ts_to = (dayofweek(timestamp('1970-01-01-00.00.00') + ts_to seconds + current timezone) - 1) * 86400 +
		hour(timestamp('1970-01-01-00.00.00') + ts_to seconds + current timezone) * 3600 +
		minute(timestamp('1970-01-01-00.00.00') + ts_to seconds + current timezone) * 60
	WHERE TYPE IN (0,1)	-- SERVICE_TIME_TYPE_UPTIME, SERVICE_TIME_TYPE_DOWNTIME
/
