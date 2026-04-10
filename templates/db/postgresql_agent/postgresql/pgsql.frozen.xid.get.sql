SELECT row_to_json(T)
FROM (
	SELECT
		extract(epoch FROM now())::integer AS ts,
		(max(age(d.datfrozenxid))::double precision /
		current_setting('autovacuum_freeze_max_age')::bigint) * 100
		AS prc_before_av,
		(max(age(d.datfrozenxid))::double precision /
		(1::bigint << (min(t.typlen)*8))) * 100
		AS prc_before_stop
	FROM pg_database d CROSS JOIN pg_type t
	WHERE t.typname = 'xid'
) T;
