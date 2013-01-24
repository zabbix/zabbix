ALTER TABLE ONLY services_times ALTER timeid DROP DEFAULT,
				ALTER serviceid DROP DEFAULT;
DELETE FROM services_times WHERE NOT EXISTS (SELECT 1 FROM services WHERE services.serviceid=services_times.serviceid);
ALTER TABLE ONLY services_times ADD CONSTRAINT c_services_times_1 FOREIGN KEY (serviceid) REFERENCES services (serviceid) ON DELETE CASCADE;

UPDATE services_times
	SET ts_from = 86400 * EXTRACT(dow FROM to_timestamp(ts_from)::timestamp) + (ts_from - EXTRACT(EPOCH FROM to_timestamp(ts_from)::date)),
		ts_to = 86400 * EXTRACT(dow FROM to_timestamp(ts_to)::timestamp) + (ts_to - EXTRACT(EPOCH FROM to_timestamp(ts_to)::date))
	WHERE type IN (0,1);
