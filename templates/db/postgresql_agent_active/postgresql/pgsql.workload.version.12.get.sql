SELECT jsonb_build_object(
	'pgss_version',
	(
		SELECT extversion
		FROM pg_extension
		WHERE extname = 'pg_stat_statements'
		LIMIT 1
	)
) AS metrics;
