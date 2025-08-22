#!/usr/bin/env perl

use strict;
use warnings;

my ($db, $table, $tsdb_compression) = @ARGV;

my @dbs = ('mysql', 'postgresql', 'timescaledb');
my @tables = ('history', 'history_uint', 'history_str', 'history_log', 'history_text');
my @tables_tsdb = ('history', 'history_uint', 'history_str', 'history_log', 'history_text', 'trends');

my %mysql = (
	'alter_table' => 'RENAME TABLE %TBL TO %TBL_old;',
	'create_table_begin' => 'CREATE TABLE `%TBL` (',
	'create_table_end' => ') ENGINE=InnoDB;',
	'pk_constraint' => "\t" . 'PRIMARY KEY (itemid,clock,ns)',
	'history' => <<'HEREDOC'
	`itemid` bigint unsigned NOT NULL,
	`clock` integer DEFAULT '0' NOT NULL,
	`value` DOUBLE PRECISION DEFAULT '0.0000' NOT NULL,
	`ns` integer DEFAULT '0' NOT NULL,
HEREDOC
	, 'history_uint' => <<'HEREDOC'
	`itemid` bigint unsigned NOT NULL,
	`clock` integer DEFAULT '0' NOT NULL,
	`value` bigint unsigned DEFAULT '0' NOT NULL,
	`ns` integer DEFAULT '0' NOT NULL,
HEREDOC
	, 'history_str' => <<'HEREDOC'
	`itemid` bigint unsigned NOT NULL,
	`clock` integer DEFAULT '0' NOT NULL,
	`value` varchar(255) DEFAULT '' NOT NULL,
	`ns` integer DEFAULT '0' NOT NULL,
HEREDOC
	, 'history_log' => <<'HEREDOC'
	`itemid` bigint unsigned NOT NULL,
	`clock` integer DEFAULT '0' NOT NULL,
	`timestamp` integer DEFAULT '0' NOT NULL,
	`source` varchar(64) DEFAULT '' NOT NULL,
	`severity` integer DEFAULT '0' NOT NULL,
	`value` text NOT NULL,
	`logeventid` integer DEFAULT '0' NOT NULL,
	`ns` integer DEFAULT '0' NOT NULL,
HEREDOC
	, 'history_text' => <<'HEREDOC'
	`itemid` bigint unsigned NOT NULL,
	`clock` integer DEFAULT '0' NOT NULL,
	`value` text NOT NULL,
	`ns` integer DEFAULT '0' NOT NULL,
HEREDOC
);

my %postgresql = (
	'alter_table' => 'ALTER TABLE %TBL RENAME TO %TBL_old;',
	'create_table_begin' => 'CREATE TABLE %TBL (',
	'create_table_end' => ');',
	'pk_constraint' => "\t" . 'PRIMARY KEY (%HISTPK)',
	'history' => <<'HEREDOC'
	itemid                   bigint                                    NOT NULL,
	clock                    integer         DEFAULT '0'               NOT NULL,
	value                    DOUBLE PRECISION DEFAULT '0.0000'          NOT NULL,
	ns                       integer         DEFAULT '0'               NOT NULL,
HEREDOC
	, 'history_uint' => <<'HEREDOC'
	itemid                   bigint                                    NOT NULL,
	clock                    integer         DEFAULT '0'               NOT NULL,
	value                    numeric(20)     DEFAULT '0'               NOT NULL,
	ns                       integer         DEFAULT '0'               NOT NULL,
HEREDOC
	, 'history_str' => <<'HEREDOC'
	itemid                   bigint                                    NOT NULL,
	clock                    integer         DEFAULT '0'               NOT NULL,
	value                    varchar(255)    DEFAULT ''                NOT NULL,
	ns                       integer         DEFAULT '0'               NOT NULL,
HEREDOC
	, 'history_log' => <<'HEREDOC'
	itemid                   bigint                                    NOT NULL,
	clock                    integer         DEFAULT '0'               NOT NULL,
	timestamp                integer         DEFAULT '0'               NOT NULL,
	source                   varchar(64)     DEFAULT ''                NOT NULL,
	severity                 integer         DEFAULT '0'               NOT NULL,
	value                    text            DEFAULT ''                NOT NULL,
	logeventid               integer         DEFAULT '0'               NOT NULL,
	ns                       integer         DEFAULT '0'               NOT NULL,
HEREDOC
	, 'history_text' => <<'HEREDOC'
	itemid                   bigint                                    NOT NULL,
	clock                    integer         DEFAULT '0'               NOT NULL,
	value                    text            DEFAULT ''                NOT NULL,
	ns                       integer         DEFAULT '0'               NOT NULL,
HEREDOC
	, 'trends' => <<'HEREDOC'
	itemid                   bigint                                    NOT NULL,
	clock                    integer         DEFAULT '0'               NOT NULL,
	num                      integer         DEFAULT '0'               NOT NULL,
	value_min                DOUBLE PRECISION DEFAULT '0.0000'          NOT NULL,
	value_avg                DOUBLE PRECISION DEFAULT '0.0000'          NOT NULL,
	value_max                DOUBLE PRECISION DEFAULT '0.0000'          NOT NULL,
HEREDOC
);

my $tsdb_compress_sql = <<'HEREDOC'
	PERFORM set_integer_now_func('%HISTTBL', 'zbx_ts_unix_now', true);

	-- extversion is a version string in format "2.19.5"
	SELECT extversion INTO tsdb_version FROM pg_extension WHERE extname = 'timescaledb';

	tsdb_version_major := substring(tsdb_version, '^\d+')::INTEGER;
	tsdb_version_minor := substring(tsdb_version, '^\d+\.(\d+)')::INTEGER;

	-- Check if TimescaleDB version is greater than or equal to 2.18.x
	IF tsdb_version_major > 2 OR (tsdb_version_major = 2 AND tsdb_version_minor >= 18)
	THEN
		-- Available since TimescaleDB 2.18.0
		ALTER TABLE %HISTTBL
		SET (
			timescaledb.enable_columnstore=true,
			timescaledb.segmentby='itemid',
			timescaledb.orderby='%COMPRESS_ORDERBY'
		);

		-- application_name is like 'Columnstore Policy%' in the newer TimescaleDB versions.
		-- application_name is like 'Compression%' in the older TimescaleDB versions
		-- before around TimescaleDB 2.18.
		SELECT extract(epoch FROM (config::json->>'compress_after')::interval)::integer
		INTO compress_after
		FROM timescaledb_information.jobs
		WHERE (application_name LIKE 'Columnstore Policy%%' OR application_name LIKE 'Compression%%')
			AND hypertable_schema = 'public'
			AND hypertable_name = '%HISTTBL_old';

		-- Available since TimescaleDB 2.18.0
		CALL add_columnstore_policy('%HISTTBL', after => compress_after);

		SELECT job_id
		INTO jobid
		FROM timescaledb_information.jobs
		WHERE (application_name LIKE 'Columnstore Policy%%' OR application_name LIKE 'Compression%%')
			AND hypertable_schema = 'public'
			AND hypertable_name = '%HISTTBL';
	ELSE
		-- Deprecated since TimescaleDB 2.18.0
		ALTER TABLE %HISTTBL
		SET (
			timescaledb.compress,
			timescaledb.compress_segmentby='itemid',
			timescaledb.compress_orderby='%COMPRESS_ORDERBY'
		);

		SELECT add_compression_policy('%HISTTBL', (
			SELECT extract(epoch FROM (config::json->>'compress_after')::interval)
			FROM timescaledb_information.jobs
			WHERE application_name LIKE 'Compression%%' AND hypertable_schema='public'
				AND hypertable_name='%HISTTBL_old'
			)::integer
		) INTO jobid;
	END IF;

	IF jobid IS NULL
	THEN
		RAISE EXCEPTION 'Failed to add compression policy';
	END IF;

	PERFORM alter_job(jobid, scheduled => true, next_start => now());
HEREDOC
;

my $tsdb = <<'HEREDOC'
\set ON_ERROR_STOP on

\copy (select * from %HISTTBL_old) TO '/tmp/%HISTTBL.csv' DELIMITER ',' CSV;

CREATE TEMP TABLE temp_%HISTTBL (
%TEMPTBLDDL
);

\copy temp_%HISTTBL FROM '/tmp/%HISTTBL.csv' DELIMITER ',' CSV

DO $$
DECLARE
	jobid			INTEGER;
	tsdb_version		TEXT;
	tsdb_version_major	INTEGER;
	tsdb_version_minor	INTEGER;
	compress_after		INTEGER;
BEGIN
	PERFORM create_hypertable('%HISTTBL', 'clock', chunk_time_interval => (
		SELECT integer_interval FROM timescaledb_information.dimensions WHERE hypertable_name='%HISTTBL_old'
	), migrate_data => true);

	INSERT INTO %HISTTBL SELECT * FROM temp_%HISTTBL ON CONFLICT (%HISTPK) DO NOTHING;

%COMPRESS
END $$;

%CONFIG_COMPR
HEREDOC
;

sub output_table {
	my ($db, $tbl, $pk_substitute_tbl) = @_;
	my $alter_table = @$db{'alter_table'};

	$alter_table =~ s/%TBL/$tbl/g;

	my $create_table = @$db{'create_table_begin'};
	$create_table =~ s/%TBL/$tbl/g;

	my $pk_constraint = @$db{'pk_constraint'};
	if ($pk_substitute_tbl == 1)
	{
		my $utbl = uc($tbl);
		$pk_constraint =~ s/%UTBL/$utbl/g;
	}

	if ($tbl eq 'trends')
	{
		$pk_constraint =~ s/%HISTPK/itemid,clock/g;
	}
	else
	{
		$pk_constraint =~ s/%HISTPK/itemid,clock,ns/g;
	}

	my $create_table_end = @$db{'create_table_end'};

	print $alter_table . "\n";
	print $create_table . "\n";
	print @$db{$tbl};
	print $pk_constraint . "\n";
	print $create_table_end . "\n\n";
}

sub output_tsdb {
	my ($tbl) = @_;

	my $tsdb_out = $tsdb;

	if ($tbl eq 'trends')
	{
		$tsdb_compress_sql =~ s/%COMPRESS_ORDERBY/clock/g;
		$tsdb_out =~ s/%HISTPK/itemid,clock/g;
	}
	else
	{
		$tsdb_compress_sql =~ s/%COMPRESS_ORDERBY/clock,ns/g;
		$tsdb_out =~ s/%HISTPK/itemid,clock,ns/g;
	}

	if ((defined $tsdb_compression) && $tsdb_compression eq 'with_compression')
	{
		$tsdb_out =~ s/%COMPRESS/$tsdb_compress_sql/g;
		$tsdb_out =~ s/%CONFIG_COMPR/UPDATE settings SET value_int=1 WHERE name='compression_status';/g;
	}
	else
	{
		$tsdb_out =~ s/%COMPRESS//g;
		$tsdb_out =~ s/%CONFIG_COMPR/UPDATE settings SET value_int=0 WHERE name='compression_status';/g;
	}

	my $temp_ddl = $postgresql{$tbl};
	chomp($temp_ddl);
	$temp_ddl =~ s/,$//;
	$tsdb_out =~ s/%TEMPTBLDDL/$temp_ddl/g;
	$tsdb_out =~ s/%HISTTBL/$tbl/g;
	print $tsdb_out;
}

sub validate_args {
	die 'No arguments were provided' if (!$db);
	die 'Wrong database was provided' if (! grep { $_ eq $db } @dbs);
}

validate_args();

if ($db eq 'timescaledb' && (defined $tsdb_compression))
{
	output_tsdb($table);
}
else
{
	if ($db eq 'mysql')
	{
		foreach my $tbl (@tables)
		{
			output_table(\%mysql, $tbl, 0);
		}
	}
	elsif ($db eq 'postgresql')
	{
		foreach my $tbl (@tables)
		{
			output_table(\%postgresql, $tbl, 0);
		}
	}
	elsif ($db eq 'timescaledb')
	{
		foreach my $tbl (@tables_tsdb)
		{
			output_table(\%postgresql, $tbl, 0);
		}
	}
}

