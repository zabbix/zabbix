DO LANGUAGE plpgsql $$
DECLARE
	ver integer;
	res text;
BEGIN
	SELECT current_setting('server_version_num') INTO ver;

	IF (ver >= 170000) THEN
		SELECT row_to_json(T) INTO res from (
			SELECT
				psc.num_timed AS checkpoints_timed,
				psc.num_requested AS checkpoints_req,
				psc.write_time AS checkpoint_write_time,
				psc.sync_time AS checkpoint_sync_time,
				psc.buffers_written AS buffers_checkpoint,
				psb.buffers_clean AS buffers_clean,
				psb.maxwritten_clean AS maxwritten_clean,
				psb.buffers_alloc AS buffers_alloc
			FROM
				pg_stat_checkpointer AS psc,
				pg_stat_bgwriter AS psb) T;

	ELSE
		SELECT row_to_json(T) INTO res from (
			SELECT
				checkpoints_timed,
				checkpoints_req,
				checkpoint_write_time,
				checkpoint_sync_time,
				buffers_checkpoint AS buffers_checkpoint,
				buffers_clean AS buffers_clean,
				maxwritten_clean,
				buffers_backend AS buffers_backend,
				buffers_backend_fsync,
				buffers_alloc AS buffers_alloc
			FROM pg_stat_bgwriter) T;
	END IF;

	perform set_config('zbx_tmp.bgwriter_json_res', res, false);
END $$;

select current_setting('zbx_tmp.bgwriter_json_res');
