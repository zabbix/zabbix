DO LANGUAGE plpgsql $$
DECLARE
	ver integer;
	res text := '{}';
BEGIN
	SELECT current_setting('server_version_num') INTO ver;

	IF (ver >= 140000) THEN
		SELECT row_to_json(T) INTO res FROM (
			SELECT
				wal_records,
				wal_fpi,
				wal_bytes,
				wal_buffers_full,
				coalesce(extract(epoch FROM stats_reset)::integer, 0) AS stats_reset
			FROM pg_stat_wal
			) T;
	END IF;

	PERFORM set_config('zbx_tmp.stat_wal_res', res, false);
END $$;

SELECT current_setting('zbx_tmp.stat_wal_res');
