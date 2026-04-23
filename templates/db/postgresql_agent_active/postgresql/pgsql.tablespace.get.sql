SELECT json_build_object(
	'data', COALESCE(
		json_agg(
			json_build_object(
				'{#PG_MAJOR}', substring(current_setting('server_version') FROM '^[0-9]+'),
				'name', t.spcname,
				'oid', t.oid,
				'owner', pg_get_userbyid(t.spcowner),
				'location', COALESCE(pg_tablespace_location(t.oid), '(none)'),
				'size_bytes', COALESCE(pg_tablespace_size(t.oid), 0),
				'is_default', t.spcname IN ('pg_default', 'pg_global')
			)
		),
		'[]'::json
	)
) AS result
FROM pg_tablespace t;
