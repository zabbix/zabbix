SELECT
	'{"data":' ||
	regexp_replace(
		coalesce(json_agg(
			json_build_object(
				'{#TABLESPACE.NAME}', spcname,
				'{#TABLESPACE.IS_DEFAULT}',
					CASE
						WHEN spcname IN ('pg_default', 'pg_global') THEN 1
						ELSE 0
					END,
				'{#TABLESPACE.OID}', oid,
				'{#PG_MAJOR}', substring(current_setting('server_version') FROM '^[0-9]+')
			)
		), '[]'::json)::text,
		E'[\\n\\r\\s]+', '', 'g'
	) || '}'
FROM pg_catalog.pg_tablespace;
