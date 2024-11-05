WITH T AS
	(SELECT db.datname,
			coalesce(T.query_time_max, 0) query_time_max,
			coalesce(T.tx_time_max, 0) tx_time_max,
			coalesce(T.mro_time_max, 0) mro_time_max,
			coalesce(T.query_time_sum, 0) query_time_sum,
			coalesce(T.tx_time_sum, 0) tx_time_sum,
			coalesce(T.mro_time_sum, 0) mro_time_sum,
			coalesce(T.query_slow_count, 0) query_slow_count,
			coalesce(T.tx_slow_count, 0) tx_slow_count,
			coalesce(T.mro_slow_count, 0) mro_slow_count
	FROM pg_database db NATURAL
	LEFT JOIN (
		SELECT datname,
			extract(epoch FROM now())::integer ts,
			coalesce(max(extract('epoch' FROM (clock_timestamp() - query_start))::integer * (state NOT IN ('idle', 'idle in transaction', 'idle in transaction (aborted)') AND query !~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) query_time_max,
			coalesce(max(extract('epoch' FROM (clock_timestamp() - query_start))::integer * (state NOT IN ('idle') AND query !~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) tx_time_max,
			coalesce(max(extract('epoch' FROM (clock_timestamp() - query_start))::integer * (state NOT IN ('idle') AND query ~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) mro_time_max,
			coalesce(sum(extract('epoch' FROM (clock_timestamp() - query_start))::integer * (state NOT IN ('idle', 'idle in transaction', 'idle in transaction (aborted)') AND query !~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) query_time_sum,
			coalesce(sum(extract('epoch' FROM (clock_timestamp() - query_start))::integer * (state NOT IN ('idle') AND query !~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) tx_time_sum,
			coalesce(sum(extract('epoch' FROM (clock_timestamp() - query_start))::integer * (state NOT IN ('idle') AND query ~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) mro_time_sum,

			coalesce(sum((extract('epoch' FROM (clock_timestamp() - query_start)) > :tmax)::integer * (state NOT IN ('idle', 'idle in transaction', 'idle in transaction (aborted)') AND query !~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) query_slow_count,
			coalesce(sum((extract('epoch' FROM (clock_timestamp() - query_start)) > :tmax)::integer * (state NOT IN ('idle') AND query !~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) tx_slow_count,
			coalesce(sum((extract('epoch' FROM (clock_timestamp() - query_start)) > :tmax)::integer * (state NOT IN ('idle') AND query ~* E'^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)')::integer), 0) mro_slow_count
		FROM pg_stat_activity
		WHERE pid <> pg_backend_pid()
		GROUP BY 1) T
	WHERE NOT db.datistemplate )
SELECT json_object_agg(datname, row_to_json(T))
FROM T
