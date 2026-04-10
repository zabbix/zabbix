WITH
	replication_count AS (
		SELECT count(*) AS value FROM pg_stat_replication
	),
	recovery_role AS (
		SELECT pg_is_in_recovery()::int AS value
	),
	replication_lag AS (
		SELECT
			CASE
				WHEN NOT pg_is_in_recovery() THEN 0
				ELSE COALESCE(EXTRACT(EPOCH FROM now() - pg_last_xact_replay_timestamp())::int, 0)
			END AS value
	),
	replication_lag_bytes AS (
		SELECT
			CASE
				WHEN NOT pg_is_in_recovery() THEN 0
				ELSE COALESCE(
					pg_wal_lsn_diff(pg_last_wal_receive_lsn(), pg_last_wal_replay_lsn())::bigint,
					0
				)
			END AS value
	),
	replication_status AS (
		SELECT
			CASE
				WHEN NOT pg_is_in_recovery() THEN
					CASE
						WHEN (SELECT count(*) FROM pg_stat_replication) > 0 THEN 1
						ELSE 2
					END
				WHEN (SELECT count(*) FROM pg_stat_wal_receiver) > 0 THEN 1
				ELSE 0
			END AS value
	)
SELECT
	jsonb_build_object(
		'replication_count', rc.value,
		'recovery_role', rr.value,
		'replication_lag_sec', rl.value,
		'replication_lag_bytes', rlb.value,
		'replication_status', rs.value
	) AS metrics
FROM
	replication_count rc,
	recovery_role rr,
	replication_lag rl,
	replication_lag_bytes rlb,
	replication_status rs;
