SELECT
	json_agg (data)
from (
	select
		subid,
		subname,
		apply_error_count,
		sync_error_count,
		COALESCE(
			EXTRACT(EPOCH FROM stats_reset),
			0
		) as stats_reset,
		substring(current_setting('server_version') FROM '^[0-9]+') as "{#PG_MAJOR}"
	from pg_stat_subscription_stats
) data;
