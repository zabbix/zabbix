DO LANGUAGE plpgsql $$
DECLARE
	ver integer;
	res text := '{}';
BEGIN
	SELECT current_setting('server_version_num') INTO ver;

	IF (ver >= 140000) THEN
		SELECT row_to_json(T) INTO res FROM (
			SELECT
				count(*) AS slots_total,
				count(*) FILTER (WHERE active) AS slots_active,
				count(*) FILTER (WHERE NOT active) AS slots_inactive,
				coalesce(min(safe_wal_size), 0) AS min_safe_wal_size,
				coalesce(count(*) FILTER (WHERE NOT active AND restart_lsn IS NOT NULL), 0)
				AS inactive_retaining_slots,
				coalesce(max(pg_wal_lsn_diff(
					CASE WHEN pg_is_in_recovery() THEN NULL ELSE pg_current_wal_lsn() END,
					restart_lsn)), 0) AS worst_slot_lag_bytes
			FROM pg_catalog.pg_replication_slots
			) T;
	END IF;

	PERFORM set_config('zbx_tmp.replication_slots_res', res, false);
END $$;

SELECT current_setting('zbx_tmp.replication_slots_res');
