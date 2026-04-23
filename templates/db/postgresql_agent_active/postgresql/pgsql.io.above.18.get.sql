SELECT json_build_object(
	'read_bytes', SUM(read_bytes),
	'write_bytes', SUM(write_bytes),
	'reads', SUM(reads),
	'writes', SUM(writes),
	'hits', SUM(hits),
	'io_time_ms', SUM(read_time + write_time),
	'wal_write_bytes', SUM(write_bytes) FILTER (WHERE object = 'wal')
) AS result
FROM pg_stat_io;
