SELECT row_to_json(T)
FROM
	(SELECT checkpoints_timed,
			checkpoints_req,
			checkpoint_write_time,
			checkpoint_sync_time,
			current_setting('block_size')::int*buffers_checkpoint AS buffers_checkpoint,
			current_setting('block_size')::int*buffers_clean AS buffers_clean,
			maxwritten_clean,
			current_setting('block_size')::int*buffers_backend AS buffers_backend,
			buffers_backend_fsync,
			current_setting('block_size')::int*buffers_alloc AS buffers_alloc
	FROM pg_stat_bgwriter) T
