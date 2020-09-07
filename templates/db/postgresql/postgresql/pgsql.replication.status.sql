DO LANGUAGE plpgsql $$
DECLARE
	ver integer;
	res text := 2;
BEGIN
	SELECT current_setting('server_version_num') INTO ver;

	IF (SELECT pg_is_in_recovery()) THEN
		IF (ver >= 90600) THEN
			SELECT * INTO res from (
				SELECT COUNT(*) FROM pg_stat_wal_receiver
				) T;
		ELSE
			res := 'ZBX_NOTSUPPORTED: Requires PostgreSQL version 9.6 or higher';
		END IF;
	END IF;

	perform set_config('zbx_tmp.repl_status_res', res, false);
END $$;

SELECT current_setting('zbx_tmp.repl_status_res');
