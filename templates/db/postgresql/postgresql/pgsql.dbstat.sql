SELECT json_object_agg(datname, row_to_json(T)) FROM (
	SELECT datname,
			numbackends AS numbackends,
			xact_commit AS xact_commit,
			xact_rollback AS xact_rollback,
			blks_read AS blks_read,
			blks_hit AS blks_hit,
			tup_returned AS tup_returned,
			tup_fetched AS tup_fetched,
			tup_inserted AS tup_inserted,
			tup_updated AS tup_updated,
			tup_deleted AS tup_deleted,
			conflicts AS conflicts,
			temp_files AS temp_files,
			temp_bytes AS temp_bytes,
			deadlocks AS deadlocks
	FROM pg_stat_database) T
