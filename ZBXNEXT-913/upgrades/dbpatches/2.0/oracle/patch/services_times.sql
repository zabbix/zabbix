ALTER TABLE services_times MODIFY timeid DEFAULT NULL;
ALTER TABLE services_times MODIFY serviceid DEFAULT NULL;
DELETE FROM services_times WHERE NOT serviceid IN (SELECT serviceid FROM services);
ALTER TABLE services_times ADD CONSTRAINT c_services_times_1 FOREIGN KEY (serviceid) REFERENCES services (serviceid) ON DELETE CASCADE;

UPDATE services_times SET
	ts_from = (to_char(cast(to_timestamp_tz('1970-01-01 00:00', 'yyyy-mm-dd tzr') + numtodsinterval(ts_from, 'second') as timestamp with local time zone), 'D') - 1) * 86400 +
		to_char(cast(to_timestamp_tz('1970-01-01 00:00', 'yyyy-mm-dd tzr') + numtodsinterval(ts_from, 'second') as timestamp with local time zone), 'HH') * 3600 +
		to_char(cast(to_timestamp_tz('1970-01-01 00:00', 'yyyy-mm-dd tzr') + numtodsinterval(ts_from, 'second') as timestamp with local time zone), 'MI') * 60,
	ts_to = (to_char(cast(to_timestamp_tz('1970-01-01 00:00', 'yyyy-mm-dd tzr') + numtodsinterval(ts_to, 'second') as timestamp with local time zone), 'D') - 1) * 86400 +
		to_char(cast(to_timestamp_tz('1970-01-01 00:00', 'yyyy-mm-dd tzr') + numtodsinterval(ts_to, 'second') as timestamp with local time zone), 'HH') * 3600 +
		to_char(cast(to_timestamp_tz('1970-01-01 00:00', 'yyyy-mm-dd tzr') + numtodsinterval(ts_to, 'second') as timestamp with local time zone), 'MI') * 60
	WHERE TYPE IN (0,1)	-- SERVICE_TIME_TYPE_UPTIME, SERVICE_TIME_TYPE_DOWNTIME
/
