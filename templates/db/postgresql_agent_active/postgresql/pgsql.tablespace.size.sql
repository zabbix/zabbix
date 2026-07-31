SELECT coalesce(json_agg(T)::text, '[]')
FROM (
	SELECT
		t.spcname AS name,
		pg_tablespace_size(t.oid) AS size_bytes,
		pg_tablespace_location(t.oid) AS location,
		pg_get_userbyid(t.spcowner) AS owner
	FROM pg_tablespace t
	) T
