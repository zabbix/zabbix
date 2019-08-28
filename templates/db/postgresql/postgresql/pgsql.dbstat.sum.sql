SELECT row_to_json(T) from (
	SELECT sum(numbackends) AS numbackends,
			sum(xact_commit) AS xact_commit,
			sum(xact_rollback) AS xact_rollback,
			sum(blks_read) AS blks_read,
			sum(blks_hit) AS blks_hit,
			sum(tup_returned) AS tup_returned,
			sum(tup_fetched) AS tup_fetched,
			sum(tup_inserted) AS tup_inserted,
			sum(tup_updated) AS tup_updated,
			sum(tup_deleted) AS tup_deleted,
			sum(conflicts) AS conflicts,
			sum(temp_files) AS temp_files,
			sum(temp_bytes) AS temp_bytes,
			sum(deadlocks) AS deadlocks
	FROM pg_stat_database) T
