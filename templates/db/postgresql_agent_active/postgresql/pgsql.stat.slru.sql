DO LANGUAGE plpgsql $$
DECLARE
	ver integer;
	res text := '[]';
BEGIN
	SELECT current_setting('server_version_num') INTO ver;

	IF (ver >= 130000) THEN
		SELECT coalesce(json_agg(T)::text, '[]') INTO res FROM (
			SELECT
				name,
				blks_read,
				blks_hit,
				blks_written,
				coalesce(extract(epoch FROM stats_reset)::integer, 0) AS stats_reset
			FROM pg_stat_slru
			) T;
	END IF;

	PERFORM set_config('zbx_tmp.stat_slru_res', res, false);
END $$;

SELECT current_setting('zbx_tmp.stat_slru_res');
