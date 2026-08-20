DO LANGUAGE plpgsql $$
DECLARE
	ver integer;
	res text := '[]';
BEGIN
	SELECT current_setting('server_version_num') INTO ver;

	IF (ver >= 150000) THEN
		SELECT coalesce(json_agg(T)::text, '[]') INTO res FROM (
			SELECT
				s.subid,
				s.subname,
				s.apply_error_count,
				s.sync_error_count,
				coalesce(extract(epoch FROM s.stats_reset)::integer, 0) AS stats_reset
			FROM pg_stat_subscription_stats s
			) T;
	END IF;

	PERFORM set_config('zbx_tmp.subscription_stats_res', res, false);
END $$;

SELECT current_setting('zbx_tmp.subscription_stats_res');
