SELECT json_build_object(
	'pgss_enabled',
	(
		EXISTS (
			SELECT 1
			FROM pg_extension
			WHERE extname = 'pg_stat_statements'
		)
		AND current_setting('shared_preload_libraries', true) LIKE '%pg_stat_statements%'
	),
	'version_num',
	current_setting('server_version_num')::int
);
