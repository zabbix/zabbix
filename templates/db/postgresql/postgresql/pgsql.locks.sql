WITH T AS
	(SELECT db.datname dbname,
			lower(replace(Q.mode, 'Lock', '')) AS MODE,
			coalesce(T.qty, 0) val
	FROM pg_database db
	JOIN (
			VALUES ('AccessShareLock') ,('RowShareLock') ,('RowExclusiveLock') ,('ShareUpdateExclusiveLock') ,('ShareLock') ,('ShareRowExclusiveLock') ,('ExclusiveLock') ,('AccessExclusiveLock')) Q(MODE) ON TRUE NATURAL
	LEFT JOIN
		(SELECT datname,
			MODE,
			count(MODE) qty
		FROM pg_locks lc
		RIGHT JOIN pg_database db ON db.oid = lc.database
		GROUP BY 1, 2) T
	WHERE NOT db.datistemplate
	ORDER BY 1, 2)
SELECT json_object_agg(dbname, row_to_json(T2))
FROM
	(SELECT dbname,
			sum(val) AS total,
			sum(CASE
					WHEN MODE = 'accessexclusive' THEN val
				END) AS accessexclusive,
			sum(CASE
					WHEN MODE = 'accessshare' THEN val
				END) AS accessshare,
			sum(CASE
					WHEN MODE = 'exclusive' THEN val
				END) AS EXCLUSIVE,
			sum(CASE
					WHEN MODE = 'rowexclusive' THEN val
				END) AS rowexclusive,
			sum(CASE
					WHEN MODE = 'rowshare' THEN val
				END) AS rowshare,
			sum(CASE
					WHEN MODE = 'share' THEN val
				END) AS SHARE,
			sum(CASE
					WHEN MODE = 'sharerowexclusive' THEN val
				END) AS sharerowexclusive,
			sum(CASE
					WHEN MODE = 'shareupdateexclusive' THEN val
				END) AS shareupdateexclusive
	FROM T
	GROUP BY dbname) T2
