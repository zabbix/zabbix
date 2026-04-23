SELECT
	json_object_agg (
		schema_name,
		json_build_object (
			'database_name', current_database(),
			'total_bytes',
			total_bytes,
			'table_count',
			table_count
		)
	)
FROM
	(
		SELECT
			n.nspname AS schema_name,
			COALESCE(SUM(pg_total_relation_size(c.oid)), 0) AS total_bytes,
			COUNT(c.oid) AS table_count
		FROM
			pg_namespace n
			LEFT JOIN pg_class c
				ON c.relnamespace = n.oid
				AND c.relkind = 'r'
		WHERE
			n.nspname NOT IN ('pg_catalog', 'information_schema')
		GROUP BY
			n.nspname
	) AS sub;
