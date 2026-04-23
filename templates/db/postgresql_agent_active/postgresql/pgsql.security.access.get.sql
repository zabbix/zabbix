SELECT
	jsonb_build_object(
		'security',
		jsonb_build_object(
			'auth_non_scram_access',
			CASE
				WHEN current_setting('password_encryption', true) <> 'scram-sha-256' THEN 1
				ELSE 0
			END,
			'host_all_access',
			CASE
				WHEN current_setting('listen_addresses', true) = '*' THEN 1
				ELSE 0
			END,
			'ssl_required_access',
			CASE
				WHEN current_setting('ssl', true) = 'on' THEN 1
				ELSE 0
			END
		)
	) AS config;
