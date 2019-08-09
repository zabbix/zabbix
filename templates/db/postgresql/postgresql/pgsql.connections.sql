DO LANGUAGE plpgsql $$
DECLARE
	ver integer;
	res text;

BEGIN
	SELECT current_setting('server_version_num') INTO ver;

	IF (ver >= 90600) THEN
		SELECT json_object_agg(datname, row_to_json(T)) INTO res from (
			SELECT
				datname,
				sum(CASE WHEN state = 'active' THEN 1 ELSE 0 END) AS active,
				sum(CASE WHEN state = 'idle' THEN 1 ELSE 0 END) AS idle,
				sum(CASE WHEN state = 'idle in transaction' THEN 1 ELSE 0 END) AS idle_in_transaction,
				count(*) AS total,
				count(*)*100/(SELECT current_setting('max_connections')::int) AS total_pct,
				sum(CASE WHEN wait_event IS NOT NULL THEN 1 ELSE 0 END) AS waiting
			FROM pg_stat_activity WHERE datid is not NULL GROUP BY datname ) T;

		ELSE
			SELECT json_object_agg(datname, row_to_json(T)) INTO res from (
				SELECT
					datname,
					sum(CASE WHEN state = 'active' THEN 1 ELSE 0 END) AS active,
					sum(CASE WHEN state = 'idle' THEN 1 ELSE 0 END) AS idle,
					sum(CASE WHEN state = 'idle in transaction' THEN 1 ELSE 0 END) AS idle_in_transaction,
					count(*) AS total,
					count(*)*100/(SELECT current_setting('max_connections')::int) AS total_pct,
					sum(CASE WHEN waiting IS TRUE THEN 1 ELSE 0 END) AS waiting
				FROM pg_stat_activity GROUP BY datname ) T;
		END IF;

	perform set_config('zbx_tmp.db_conn_json_res', res, false);

END $$;

SELECT current_setting('zbx_tmp.db_conn_json_res');
