SELECT
	json_agg(
		json_build_object(
			'name', name,
			'blks_read', blks_read,
			'blks_hit', blks_hit,
			'stats_reset', stats_reset
		)
	) AS result
FROM
	pg_stat_slru;
