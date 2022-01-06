SELECT row_to_json(T)
FROM (
	SELECT
		extract(epoch FROM now())::integer AS ts,
		((1 - max(age(d.datfrozenxid))::double precision /
		current_setting('autovacuum_freeze_max_age')::bigint) * 100)::numeric(9,6)
		AS prc_before_av,
		((1 - abs(max(age(d.datfrozenxid))::double precision /
		(1::bigint << (min(t.typlen)*8)))) * 100)::numeric(9,6)
		AS prc_before_stop
	FROM pg_database d CROSS JOIN pg_type t
	WHERE t.typname = 'xid'
) T;
