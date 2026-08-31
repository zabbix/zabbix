SELECT coalesce(json_agg(T)::text, '[]')
FROM (
	SELECT
		n.nspname AS name,
		sum(pg_total_relation_size(c.oid))::bigint AS total_bytes,
		count(*) AS table_count
	FROM pg_class c
	JOIN pg_namespace n ON n.oid = c.relnamespace
	WHERE
		c.relkind IN ('r', 'p')
		AND n.nspname NOT IN ('pg_catalog', 'information_schema')
		AND n.nspname NOT LIKE 'pg_toast%'
	GROUP BY n.nspname
	) T
