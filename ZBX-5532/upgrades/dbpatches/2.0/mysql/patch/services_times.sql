ALTER TABLE services_times MODIFY timeid bigint unsigned NOT NULL,
			   MODIFY serviceid bigint unsigned NOT NULL;
DELETE FROM services_times WHERE NOT serviceid IN (SELECT serviceid FROM services);
ALTER TABLE services_times ADD CONSTRAINT c_services_times_1 FOREIGN KEY (serviceid) REFERENCES services (serviceid) ON DELETE CASCADE;

UPDATE services_times
	SET ts_from = 86400 * DAYOFWEEK(FROM_UNIXTIME(ts_from)) + (ts_from - UNIX_TIMESTAMP(STR_TO_DATE(DATE_FORMAT(FROM_UNIXTIME(ts_from), '%Y%m%d'), '%Y%m%d'))),
		ts_to = 86400 * DAYOFWEEK(FROM_UNIXTIME(ts_to)) + (ts_to - UNIX_TIMESTAMP(STR_TO_DATE(DATE_FORMAT(FROM_UNIXTIME(ts_to), '%Y%m%d'), '%Y%m%d')))
	WHERE type IN (0,1);
