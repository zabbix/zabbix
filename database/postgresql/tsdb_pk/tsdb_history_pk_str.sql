-- Export data
\copy (select * from history_str_old) TO '/tmp/history_str.csv' DELIMITER ',' CSV;

CREATE TEMP TABLE temp_history_str (
	itemid                   bigint                                    NOT NULL,
	clock                    integer         DEFAULT '0'               NOT NULL,
	value                    varchar(255)    DEFAULT ''                NOT NULL,
	ns                       integer         DEFAULT '0'               NOT NULL
);

-- Import data
\copy temp_history_str FROM '/tmp/history_str.csv' DELIMITER ',' CSV

-- Create hypertable and populate it
SELECT create_hypertable('history_str', 'clock', chunk_time_interval => 86400, migrate_data => true);
INSERT INTO history_str SELECT * FROM temp_history_str ON CONFLICT (itemid,clock,ns) DO NOTHING;

-- Enable compression, and compress newly imported data
SELECT set_integer_now_func('history_str', 'zbx_ts_unix_now', true);
ALTER TABLE history_str SET (timescaledb.compress,timescaledb.compress_segmentby='itemid',timescaledb.compress_orderby='clock,ns');

-- In TSDBv1 chunks will be compressed automatically when policy is added and it is time for these chunks to be compressed
DO $$
DECLARE
	jobid			INTEGER;
	tsdb_version_major	INTEGER;
BEGIN
	SELECT substring(extversion, '^\d+') INTO tsdb_version_major FROM pg_extension WHERE extname='timescaledb';

	IF (tsdb_version_major < 2)
	THEN
		PERFORM add_compress_chunks_policy('history_str', (
				SELECT (p.older_than).integer_interval FROM _timescaledb_config.bgw_policy_compress_chunks p
				INNER JOIN _timescaledb_catalog.hypertable h ON (h.id=p.hypertable_id) WHERE h.table_name='history_str_old'
			)::integer
		);
	ELSE
		SELECT add_compression_policy('history_str', (
			SELECT extract(epoch FROM (config::json->>'compress_after')::interval)
			FROM timescaledb_information.jobs
			WHERE application_name LIKE 'Compression%%' AND hypertable_schema='public' AND hypertable_name='history_str_old'
			)::integer
		) INTO jobid;

		IF jobid IS NULL
		THEN
			raise exception 'Failed to add compression policy';
		END IF;

		PERFORM alter_job(jobid, scheduled => true);
		CALL run_job(jobid);
	END IF;
END $$;
