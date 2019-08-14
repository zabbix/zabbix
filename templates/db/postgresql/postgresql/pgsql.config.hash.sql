SELECT md5(
	json_build_object(
		'extensions', (
			SELECT array_agg(extname) FROM (
				SELECT extname
				FROM pg_extension
				ORDER BY extname
			) AS e
		),
		'settings', (
			SELECT json_object(array_agg(name), array_agg(setting)) FROM (
				SELECT name, setting
				FROM pg_settings
				WHERE name != 'application_name'
				ORDER BY name
			) AS s
		)
	)::text);
