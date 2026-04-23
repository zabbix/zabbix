SELECT json_build_object(
	'total_connections',
	(
		SELECT count(*)
		FROM pg_stat_activity
	),
	'active_connections',
	(
		SELECT count(*)
		FROM pg_stat_activity
		WHERE state = 'active'
	),
	'waiting_locks',
	(
		SELECT count(*)
		FROM pg_locks
		WHERE NOT granted
	),
	'max_xid_age',
	(
		SELECT max(age(datfrozenxid))
		FROM pg_database
	),
	'max_xid_percent',
	(
		SELECT round(100.0 * max(age(datfrozenxid)) / 2000000000, 6)
		FROM pg_database
	),
	'cache_hit_ratio',
	(
		SELECT round(
			100.0 * sum(blks_hit) / nullif(sum(blks_hit + blks_read), 0),
			2
		)
		FROM pg_stat_database
	),
	'deadlocks',
	(
		SELECT sum(deadlocks)
		FROM pg_stat_database
	),
	'autovacuum',
	json_build_object(
		'active',
		(
			SELECT count(*)
			FROM pg_stat_activity
			WHERE backend_type = 'autovacuum worker'
				AND state <> 'idle'
		),
		'idle',
		(
			SELECT count(*)
			FROM pg_stat_activity
			WHERE backend_type = 'autovacuum worker'
				AND state = 'idle'
		)
	)
) AS health;
