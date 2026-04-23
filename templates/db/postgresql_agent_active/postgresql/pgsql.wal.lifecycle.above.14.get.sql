WITH
	wal AS (
		SELECT
			wal_records,
			wal_fpi,
			wal_bytes,
			wal_buffers_full,
			EXTRACT(EPOCH FROM stats_reset)::bigint AS stats_reset
		FROM pg_stat_wal
	),

	rep AS (
		SELECT
			COALESCE(
				json_agg(
					json_build_object(
						'application_name', application_name,
						'state', state,
						'sent_lsn', sent_lsn,
						'replay_lsn', replay_lsn
					)
				),
				'[]'::json
			) AS replication
		FROM pg_stat_replication
	),

	slots AS (
		SELECT
			COALESCE(
				json_agg(
					json_build_object(
						'slot_name', slot_name,
						'active', active,
						'restart_lsn', restart_lsn,
						'confirmed_flush_lsn', confirmed_flush_lsn
					)
				),
				'[]'::json
			) AS slots
		FROM pg_replication_slots
	)

SELECT
	json_build_object(
		'wal', (SELECT row_to_json(wal) FROM wal),
		'replication', (SELECT replication FROM rep),
		'slots', (SELECT slots FROM slots)
	) AS result;
