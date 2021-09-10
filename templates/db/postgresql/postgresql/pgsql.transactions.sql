DO LANGUAGE plpgsql $$
DECLARE
	ver integer;
	res text;
BEGIN
	SELECT current_setting('server_version_num') INTO ver;

	IF (ver >= 90600) THEN
		SELECT row_to_json(T) INTO res from (
			SELECT
				coalesce(extract(epoch FROM max(CASE WHEN state = 'idle in transaction' THEN age(now(), query_start) END)), 0) AS idle,
				coalesce(extract(epoch FROM max(CASE WHEN state <> 'idle in transaction' AND state <> 'idle' THEN age(now(), query_start) END)), 0) AS active,
				coalesce(extract(epoch FROM max(CASE WHEN wait_event IS NOT NULL AND state='active' THEN age(now(), query_start) END)), 0) AS waiting,
				(SELECT coalesce(extract(epoch FROM max(age(now(), prepared))), 0) FROM pg_prepared_xacts) AS prepared,
				max(age(backend_xmin)) AS xmin_age
			FROM pg_stat_activity WHERE backend_type='client backend') T;
	ELSE
		SELECT row_to_json(T) INTO res from (
			SELECT
				coalesce(extract(epoch FROM max(CASE WHEN state = 'idle in transaction' THEN age(now(), query_start) END)), 0) AS idle,
				coalesce(extract(epoch FROM max(CASE WHEN state <> 'idle in transaction' AND state <> 'idle' THEN age(now(), query_start) END)), 0) AS active,
				coalesce(extract(epoch FROM max(CASE WHEN waiting IS TRUE THEN age(now(), query_start) END)), 0) AS waiting,
				(SELECT coalesce(extract(epoch FROM max(age(now(), prepared))), 0) FROM pg_prepared_xacts) AS prepared
			FROM pg_stat_activity) T;
	END IF;

	perform set_config('zbx_tmp.trans_json_res', res, false);
END $$;

SELECT current_setting('zbx_tmp.trans_json_res');
