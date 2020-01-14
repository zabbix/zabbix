SELECT json_object_agg(datname, row_to_json(T)) FROM (
	SELECT datname,
			numbackends,
			xact_commit,
			xact_rollback,
			blks_read,
			blks_hit,
			tup_returned,
			tup_fetched,
			tup_inserted,
			tup_updated,
			tup_deleted,
			conflicts,
			temp_files,
			temp_bytes,
			deadlocks
	FROM pg_stat_database where datname is not null) T
