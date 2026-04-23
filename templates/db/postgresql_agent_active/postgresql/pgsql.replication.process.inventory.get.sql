SELECT
	json_agg(t)
FROM
	(
		SELECT
			application_name,
			EXTRACT(
				EPOCH FROM COALESCE(flush_lag, INTERVAL '0')
			) AS flush_lag,
			EXTRACT(
				EPOCH FROM COALESCE(replay_lag, INTERVAL '0')
			) AS replay_lag,
			EXTRACT(
				EPOCH FROM COALESCE(write_lag, INTERVAL '0')
			) AS write_lag
		FROM
			pg_stat_replication
	) t;
