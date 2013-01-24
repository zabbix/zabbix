ALTER TABLE services_times MODIFY timeid DEFAULT NULL;
ALTER TABLE services_times MODIFY serviceid DEFAULT NULL;
DELETE FROM services_times WHERE NOT serviceid IN (SELECT serviceid FROM services);
ALTER TABLE services_times ADD CONSTRAINT c_services_times_1 FOREIGN KEY (serviceid) REFERENCES services (serviceid) ON DELETE CASCADE;

UPDATE services_times
	SET ts_from = to_char(to_timestamp_tz('197001010000000000','yyyymmddhh24missTZHTZM')+NumToDSInterval(ts_from,'SECOND'), 'D') * 86400 - 86400
			+ (ts_from -
				(to_date(to_char(to_timestamp_tz('197001010000000000','yyyymmddhh24missTZHTZM')+NumToDSInterval(ts_from,'SECOND'), 'yyyymmdd'), 'yyyymmdd') - TO_DATE('19700101000000','YYYYMMDDHH24MISS')) / (1/86400)),
		ts_to = to_char(to_timestamp_tz('197001010000000000','yyyymmddhh24missTZHTZM')+NumToDSInterval(ts_to,'SECOND'), 'D') * 86400 - 86400
			+ (ts_to -
				(to_date(to_char(to_timestamp_tz('197001010000000000','yyyymmddhh24missTZHTZM')+NumToDSInterval(ts_to,'SECOND'), 'yyyymmdd'), 'yyyymmdd') - TO_DATE('19700101000000','YYYYMMDDHH24MISS')) / (1/86400))
	WHERE type IN (0,1);
