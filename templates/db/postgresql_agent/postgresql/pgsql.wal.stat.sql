DO LANGUAGE plpgsql $$
DECLARE
	ver integer;
	res text := '{"write":0,"count":0}';
BEGIN
	SELECT current_setting('server_version_num') INTO ver;

	IF (SELECT NOT pg_is_in_recovery()) THEN
		IF (ver >= 100000) THEN
			SELECT row_to_json(T) INTO res FROM (
				SELECT pg_wal_lsn_diff(pg_current_wal_lsn(),'0/00000000') AS WRITE,
				count(*) FROM pg_ls_waldir() AS COUNT
				) T;

		ELSE
			SELECT row_to_json(T) INTO res FROM (
				SELECT pg_xlog_location_diff(pg_current_xlog_location(),'0/00000000') AS WRITE,
				count(*) FROM pg_ls_dir('pg_xlog') AS COUNT
				) T;
		END IF;
	END IF;

	perform set_config('zbx_tmp.wal_json_res', res, false);
END $$;

select current_setting('zbx_tmp.wal_json_res');
