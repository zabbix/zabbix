WITH stats AS (
	SELECT
		SUM(calls) AS total_calls,
		AVG(total_exec_time / NULLIF(calls, 0)) AS avg_exec_time_ms,
		MAX(total_exec_time / NULLIF(calls, 0)) AS max_exec_time_ms,
		SUM(temp_blks_written) AS temp_blks_written,
		COALESCE(SUM(rows) FILTER (WHERE query LIKE 'INSERT%'), 0) AS rows_inserted,
		COALESCE(SUM(rows) FILTER (WHERE query LIKE 'UPDATE%'), 0) AS rows_updated,
		COALESCE(SUM(rows) FILTER (WHERE query LIKE 'DELETE%'), 0) AS rows_deleted
	FROM pg_stat_statements
)
SELECT jsonb_build_object(
	'pgss_version',
	(
		SELECT extversion
		FROM pg_extension
		WHERE extname = 'pg_stat_statements'
		LIMIT 1
	),
	'metrics',
	jsonb_build_object(
		'total_calls', total_calls,
		'avg_exec_time_ms', avg_exec_time_ms,
		'max_exec_time_ms', max_exec_time_ms,
		'temp_blks_written', temp_blks_written,
		'rows_inserted', rows_inserted,
		'rows_updated', rows_updated,
		'rows_deleted', rows_deleted
	)
) AS metrics
FROM stats;
