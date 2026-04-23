WITH T AS (
	SELECT datname AS dbname
	FROM pg_database
	WHERE NOT datistemplate
)
SELECT '{"data":' ||
	regexp_replace(
		coalesce(json_agg(
			json_build_object(
				'{#DBNAME}', T.dbname,
				'{#PG_MAJOR}', substring(current_setting('server_version') FROM '^[0-9]+')
			)
		), '[]'::json)::text,
		E'[\\n\\r\\s]+', '', 'g'
	) || '}'
FROM T;
