WITH	pgss_config AS (
	SELECT
		current_setting('pg_stat_statements.track', true) AS track_level,
		COALESCE(current_setting('pg_stat_statements.track_nested', true), '0') AS track_nested,
		current_setting('pg_stat_statements.max', true) AS max_statements
),
stats AS (
	SELECT
		COUNT(*) AS distinct_statements,
		SUM(calls) AS total_calls,
		AVG(total_exec_time / NULLIF(calls, 0)) AS avg_exec_time_ms,
		MAX(total_exec_time / NULLIF(calls, 0)) AS max_exec_time_ms,
		SUM(temp_blks_read) AS temp_blks_read,
		SUM(temp_blks_written) AS temp_blks_written,
		SUM(plans) AS total_plans,
		SUM(total_plan_time) AS total_plan_time_ms,
		COALESCE(AVG(total_plan_time / NULLIF(plans, 0)), 0) AS avg_plan_time_ms,
		COALESCE(SUM(rows) FILTER (WHERE query LIKE 'INSERT%'), 0) AS rows_inserted,
		COALESCE(SUM(rows) FILTER (WHERE query LIKE 'UPDATE%'), 0) AS rows_updated,
		COALESCE(SUM(rows) FILTER (WHERE query LIKE 'DELETE%'), 0) AS rows_deleted
	FROM pg_stat_statements
)
SELECT jsonb_build_object(
	'pg_stat_statements',
	jsonb_build_object(
		'extension_version',
		(
			SELECT extversion
			FROM pg_extension
			WHERE extname = 'pg_stat_statements'
		),
		'configuration',
		(
			SELECT jsonb_build_object(
				'track', track_level,
				'track_nested', track_nested,
				'max', max_statements
			)
			FROM pgss_config
		)
	),
	'metrics',
	jsonb_build_object(
		'distinct_statements', distinct_statements,
		'total_calls', total_calls,
		'execution_time_ms', jsonb_build_object(
			'avg', avg_exec_time_ms,
			'max', max_exec_time_ms
		),
		'plan_time_ms', jsonb_build_object(
			'total', total_plan_time_ms,
			'avg', avg_plan_time_ms,
			'plans', total_plans
		),
		'temp_io_blocks', jsonb_build_object(
			'read', temp_blks_read,
			'written', temp_blks_written
		),
		'rows_affected', jsonb_build_object(
			'inserted', rows_inserted,
			'updated', rows_updated,
			'deleted', rows_deleted
		)
	)
) AS metrics
FROM stats;
