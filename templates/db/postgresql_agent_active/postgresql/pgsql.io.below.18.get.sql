SELECT json_build_object(
	'reads', SUM(reads),
	'writes', SUM(writes)
) AS result
FROM pg_stat_io;
