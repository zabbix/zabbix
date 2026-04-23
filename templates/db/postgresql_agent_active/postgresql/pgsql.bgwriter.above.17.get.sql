SELECT row_to_json(T)
FROM (
	SELECT
		EXTRACT(EPOCH FROM pg_postmaster_start_time())::bigint AS postmaster_start_time,
		psc.num_timed AS checkpoints_timed,
		psc.num_requested AS checkpoints_req,
		psc.write_time AS checkpoint_write_time,
		psc.sync_time AS checkpoint_sync_time,
		psc.buffers_written AS buffers_checkpoint,
		psb.buffers_clean AS buffers_clean,
		psb.maxwritten_clean AS maxwritten_clean,
		psb.buffers_alloc AS buffers_alloc
	FROM pg_stat_checkpointer AS psc,
	pg_stat_bgwriter AS psb
) T;
