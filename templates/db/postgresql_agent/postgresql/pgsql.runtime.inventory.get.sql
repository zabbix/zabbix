SELECT
	jsonb_build_object(
		'network',
		jsonb_build_object(
			'tcp_keepalives_idle', current_setting('tcp_keepalives_idle', true),
			'tcp_keepalives_interval', current_setting('tcp_keepalives_interval', true),
			'tcp_keepalives_count', current_setting('tcp_keepalives_count', true),
			'tcp_user_timeout', current_setting('tcp_user_timeout', true),
			'client_connection_check_interval', current_setting('client_connection_check_interval', true)
		),

		'ssl',
		jsonb_build_object(
			'ssl_prefer_server_ciphers', current_setting('ssl_prefer_server_ciphers', true),
			'ssl_min_protocol_version', current_setting('ssl_min_protocol_version', true)
		),

		'unix_socket',
		jsonb_build_object(
			'unix_socket_directories', current_setting('unix_socket_directories', true),
			'unix_socket_permissions', current_setting('unix_socket_permissions', true)
		)
	) AS config;
