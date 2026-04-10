SELECT row_to_json(T)
FROM (
	SELECT
		sum(CASE WHEN state = 'active' THEN 1 ELSE 0 END) AS active,
		sum(CASE WHEN state = 'idle' THEN 1 ELSE 0 END) AS idle,
		sum(CASE WHEN state = 'idle in transaction' THEN 1 ELSE 0 END) AS idle_in_transaction,
		sum(CASE WHEN state = 'idle in transaction (aborted)' THEN 1 ELSE 0 END) AS idle_in_transaction_aborted,
		sum(CASE WHEN backend_type = 'client backend' THEN 1 ELSE 0 END) AS disabled,
		sum(CASE WHEN backend_type = 'parallel worker' THEN 1 ELSE 0 END) AS fastpath_function_call,
		count(*) AS total,
		count(*) * 100 / NULLIF(current_setting('max_connections')::int, 0) AS total_pct,
		sum(
			CASE
				WHEN wait_event IS NOT NULL AND state != 'idle' THEN 1
				WHEN wait_event IS NOT NULL AND state != 'idle' THEN 1
				ELSE 0
			END
		) AS waiting,
		(SELECT count(*) FROM pg_prepared_xacts) AS prepared
	FROM pg_stat_activity
	WHERE datid IS NOT NULL
) T;
