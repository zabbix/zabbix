WITH v AS (
	SELECT
		CAST(4096/CEIL(CAST(ps.setting AS numeric)/1024/1024) AS int) AS segment_parts_count,
		CAST(ps.setting AS bigint) AS segment_size,
		CAST(CAST('x'||SUBSTRING(pa.last_archived_wal FROM 9 FOR 8) AS bit(32)) AS int) AS last_wal_div,
		CAST(CAST('x'||SUBSTRING(pa.last_archived_wal FROM 17 FOR 8) AS bit(32)) AS int) AS last_wal_mod,
		CASE WHEN pg_is_in_recovery() THEN NULL ELSE CAST(CAST('x'||SUBSTRING(pg_walfile_name(pg_current_wal_lsn()) FROM 9 FOR 8) AS bit(32)) AS int) END AS current_wal_div,
		CASE WHEN pg_is_in_recovery() THEN NULL ELSE CAST(CAST('x'||SUBSTRING(pg_walfile_name(pg_current_wal_lsn()) FROM 17 FOR 8) AS bit(32)) AS int) END AS current_wal_mod
	FROM pg_settings ps
	CROSS JOIN pg_stat_archiver pa
	WHERE ps.name='wal_segment_size'
)

SELECT json_build_object(
	'archived_count',pg_stat_archiver.archived_count,
	'failed_count',pg_stat_archiver.failed_count,
	'last_archived_time',COALESCE(EXTRACT(EPOCH FROM pg_stat_archiver.last_archived_time),0),
	'last_failed_time',COALESCE(EXTRACT(EPOCH FROM pg_stat_archiver.last_failed_time),0),
	'count_files',
	(
		SELECT GREATEST(
			COALESCE(
				(segment_parts_count-last_wal_mod)+((current_wal_div-last_wal_div-1)*segment_parts_count)+current_wal_mod-1,
				0
			),
			0
		)
		FROM v
	),
	'size_files',
	(
		SELECT GREATEST(
			COALESCE(
				((segment_parts_count-last_wal_mod)+((current_wal_div-last_wal_div-1)*segment_parts_count)+current_wal_mod-1)*segment_size,
				0
			),
			0
		)
		FROM v
	)
)
FROM pg_stat_archiver;
