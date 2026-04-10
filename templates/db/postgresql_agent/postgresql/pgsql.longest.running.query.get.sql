SELECT json_build_object(
	'pid', COALESCE(t.pid, 0),
	'duration', COALESCE(t.duration, 0),
	'query', COALESCE(t.query, '')
)
FROM (
	SELECT
		pid,
		EXTRACT(EPOCH FROM now() - query_start) AS duration,
		query
	FROM pg_stat_activity
	WHERE state = 'active'
		AND query NOT LIKE 'START_REPLICATION SLOT%'
		AND query NOT ILIKE '%pg_stat%'
		AND query NOT ILIKE '%json_object_agg%'
	ORDER BY now() - query_start DESC
	LIMIT 1
) t

UNION ALL

SELECT json_build_object(
	'pid', 0,
	'duration', 0,
	'query', ''
)
WHERE NOT EXISTS (
	SELECT 1
	FROM pg_stat_activity
	WHERE state = 'active'
		AND query NOT LIKE 'START_REPLICATION SLOT%'
		AND query NOT ILIKE '%pg_stat%'
		AND query NOT ILIKE '%json_object_agg%'
);
