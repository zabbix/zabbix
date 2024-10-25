WITH T AS (
	SELECT
		sum(CASE WHEN relkind IN ('r', 't', 'm') THEN pg_stat_get_numscans(oid) END) seq,
		sum(CASE WHEN relkind = 'i' THEN pg_stat_get_numscans(oid) END) idx
	FROM pg_class
	WHERE relkind IN ('r', 't', 'm', 'i')
)
SELECT row_to_json(T)
FROM T
