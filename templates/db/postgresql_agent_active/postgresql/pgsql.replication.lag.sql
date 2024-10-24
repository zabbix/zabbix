SELECT
	CASE
		WHEN NOT pg_is_in_recovery() THEN
			'SELECT 0 AS value'
		WHEN current_setting('server_version_num')::integer < 100000 THEN
			'SELECT '
				'CASE WHEN pg_last_xlog_receive_location() = pg_last_xlog_replay_location() '
					'THEN 0 '
				'ELSE COALESCE(EXTRACT(EPOCH FROM now() - pg_last_xact_replay_timestamp())::integer, 0) '
				'END AS value'
		WHEN current_setting('server_version_num')::integer >= 100000 THEN
			'SELECT '
				'CASE WHEN pg_last_wal_receive_lsn() = pg_last_wal_replay_lsn() '
					'THEN 0 '
				'ELSE COALESCE(EXTRACT(EPOCH FROM now() - pg_last_xact_replay_timestamp())::integer, 0) '
				'END AS value'
	END
\gexec
