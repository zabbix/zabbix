SELECT
	row_to_json(T)
FROM
	(
		SELECT
			COUNT(*) AS slots_total,
			SUM(CASE WHEN active THEN 1 ELSE 0 END) AS slots_active,
			SUM(CASE WHEN NOT active THEN 1 ELSE 0 END) AS slots_inactive
		FROM
			pg_catalog.pg_replication_slots
	) T;
