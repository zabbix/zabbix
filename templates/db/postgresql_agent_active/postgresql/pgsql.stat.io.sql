DO LANGUAGE plpgsql $$
DECLARE
	ver integer;
	res text := '{}';
BEGIN
	SELECT current_setting('server_version_num') INTO ver;

	IF (ver >= 180000) THEN
		SELECT row_to_json(T) INTO res FROM (
			SELECT
				coalesce(sum(reads), 0) AS reads,
				coalesce(sum(writes), 0) AS writes,
				coalesce(sum(hits), 0) AS hits,
				coalesce(sum(read_bytes), 0) AS read_bytes,
				coalesce(sum(write_bytes), 0) AS write_bytes,
				coalesce(sum(fsyncs), 0) AS fsyncs,
				coalesce(sum(read_time + write_time), 0)::numeric(20,3) AS io_time_ms
			FROM pg_stat_io
			) T;

	ELSIF (ver >= 160000) THEN
		SELECT row_to_json(T) INTO res FROM (
			SELECT
				coalesce(sum(reads), 0) AS reads,
				coalesce(sum(writes), 0) AS writes,
				coalesce(sum(hits), 0) AS hits,
				coalesce(sum(reads * op_bytes), 0) AS read_bytes,
				coalesce(sum(writes * op_bytes), 0) AS write_bytes,
				coalesce(sum(fsyncs), 0) AS fsyncs,
				coalesce(sum(read_time + write_time), 0)::numeric(20,3) AS io_time_ms
			FROM pg_stat_io
			) T;
	END IF;

	PERFORM set_config('zbx_tmp.stat_io_res', res, false);
END $$;

SELECT current_setting('zbx_tmp.stat_io_res');
