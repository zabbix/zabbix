WITH T AS (
	SELECT
		datname AS "{#DBNAME}"
	FROM pg_database
	WHERE
		NOT datistemplate
		AND datname != 'postgres'
)
SELECT '{"data":'|| regexp_replace(coalesce(json_agg(T), '[]'::json)::text, E'[\\n\\r\\s]+', '', 'g') || '}'
FROM T
