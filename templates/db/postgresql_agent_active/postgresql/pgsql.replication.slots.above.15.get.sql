SELECT
	row_to_json(T)
FROM
	(
		SELECT
			COUNT(*) AS slots_total,
			COUNT(*) FILTER (WHERE active) AS slots_active,
			COUNT(*) FILTER (WHERE NOT active) AS slots_inactive,
			COALESCE(MAX(safe_wal_size), 0) AS max_safe_wal_size,
			COALESCE(
				MAX(pg_wal_lsn_diff(pg_current_wal_lsn(), restart_lsn)),
				0
			) AS worst_slot_lag_bytes,
			COUNT(*) FILTER (
				WHERE NOT active AND safe_wal_size > 0
			) AS inactive_retaining_slots
		FROM
			pg_catalog.pg_replication_slots
	) T;
