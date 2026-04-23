SELECT row_to_json(T)
FROM (
	SELECT
		pg_postmaster_start_time() AS postmaster_start_time,
		checkpoints_timed,
		checkpoints_req,
		checkpoint_write_time,
		checkpoint_sync_time,
		buffers_checkpoint,
		buffers_clean,
		maxwritten_clean,
		buffers_backend,
		buffers_backend_fsync,
		buffers_alloc
	FROM pg_stat_bgwriter
) T;
