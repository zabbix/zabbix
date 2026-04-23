WITH T AS (
	SELECT name
	FROM pg_stat_slru
)
SELECT
	'{"data":' ||
	regexp_replace(
		COALESCE(
			json_agg(
				json_build_object(
					'{#SLRU.NAME}', T.name,
					'{#PG_MAJOR}', substring(current_setting('server_version') FROM '^[0-9]+')
				)
			), '[]'::json
		)::text,
		E'[\\n\\r\\s]+', '', 'g'
	) || '}'
FROM T;
