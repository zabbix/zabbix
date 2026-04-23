WITH	stats AS (
	SELECT
		sum(calls) AS total_calls,
		avg(total_exec_time / NULLIF(calls, 0)) AS avg_exec_time_ms,
		max(total_exec_time / NULLIF(calls, 0)) AS max_exec_time_ms,
		sum(temp_blks_written) AS temp_blks_written,
		sum(shared_blks_hit) AS shared_blks_hit,
		sum(shared_blks_read) AS shared_blks_read
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
		'shared_blks_hit', shared_blks_hit,
		'shared_blks_read', shared_blks_read,
		'temp_blks_written', temp_blks_written
	)
) AS metrics
FROM stats;
