#!/usr/bin/env perl

use strict;
use warnings;

my ($db, $table, $tsdb_compression) = @ARGV;

my @dbs = ('mysql', 'oracle', 'postgresql', 'timescaledb');
my @tables = ('history', 'history_uint', 'history_str', 'history_log', 'history_text');

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

my %oracle = (
	'alter_table' => 'RENAME %TBL TO %TBL_old;',
	'create_table_begin' => 'CREATE TABLE %TBL (',
	'create_table_end' => ');',
	'pk_constraint' => "\t" . 'CONSTRAINT PK_%UTBL PRIMARY KEY (itemid,clock,ns)',
	'history' => <<'HEREDOC'
	itemid                   number(20)                                NOT NULL,
	clock                    number(10)      DEFAULT '0'               NOT NULL,
	value                    BINARY_DOUBLE   DEFAULT '0.0000'          NOT NULL,
	ns                       number(10)      DEFAULT '0'               NOT NULL,
HEREDOC
	, 'history_uint' => <<'HEREDOC'
	itemid                   number(20)                                NOT NULL,
	clock                    number(10)      DEFAULT '0'               NOT NULL,
	value                    number(20)      DEFAULT '0'               NOT NULL,
	ns                       number(10)      DEFAULT '0'               NOT NULL,
HEREDOC
	, 'history_str' => <<'HEREDOC'
	itemid                   number(20)                                NOT NULL,
	clock                    number(10)      DEFAULT '0'               NOT NULL,
	value                    nvarchar2(255)  DEFAULT ''                ,
	ns                       number(10)      DEFAULT '0'               NOT NULL,
HEREDOC
	, 'history_log' => <<'HEREDOC'
	itemid                   number(20)                                NOT NULL,
	clock                    number(10)      DEFAULT '0'               NOT NULL,
	timestamp                number(10)      DEFAULT '0'               NOT NULL,
	source                   nvarchar2(64)   DEFAULT ''                ,
	severity                 number(10)      DEFAULT '0'               NOT NULL,
	value                    nclob           DEFAULT ''                ,
	logeventid               number(10)      DEFAULT '0'               NOT NULL,
	ns                       number(10)      DEFAULT '0'               NOT NULL,
HEREDOC
	, 'history_text' => <<'HEREDOC'
	itemid                   number(20)                                NOT NULL,
	clock                    number(10)      DEFAULT '0'               NOT NULL,
	value                    nclob           DEFAULT ''                ,
	ns                       number(10)      DEFAULT '0'               NOT NULL,
HEREDOC
);

my %postgresql = (
	'alter_table' => 'ALTER TABLE %TBL RENAME TO %TBL_old;',
	'create_table_begin' => 'CREATE TABLE %TBL (',
	'create_table_end' => ');',
	'pk_constraint' => "\t" . 'PRIMARY KEY (itemid,clock,ns)',
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
);

my $tsdb_compress_sql = <<'HEREDOC'
	PERFORM set_integer_now_func('%HISTTBL', 'zbx_ts_unix_now', true);

	ALTER TABLE %HISTTBL
	SET (timescaledb.compress,timescaledb.compress_segmentby='itemid',timescaledb.compress_orderby='clock,ns');

	IF (tsdb_version_major < 2)
	THEN
		PERFORM add_compress_chunks_policy('%HISTTBL', (
				SELECT (p.older_than).integer_interval
				FROM _timescaledb_config.bgw_policy_compress_chunks p
				INNER JOIN _timescaledb_catalog.hypertable h ON (h.id=p.hypertable_id)
				WHERE h.table_name='%HISTTBL_old'
			)::integer
		);
	ELSE
		SELECT add_compression_policy('%HISTTBL', (
			SELECT extract(epoch FROM (config::json->>'compress_after')::interval)
			FROM timescaledb_information.jobs
			WHERE application_name LIKE 'Compression%%' AND hypertable_schema='public'
				AND hypertable_name='%HISTTBL_old'
			)::integer
		) INTO jobid;

		IF jobid IS NULL
		THEN
			RAISE EXCEPTION 'Failed to add compression policy';
		END IF;

		PERFORM alter_job(jobid, scheduled => true, next_start => now());
	END IF;
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
	tsdb_version_major	INTEGER;
	chunk_tm_interval	INTEGER;
	jobid			INTEGER;
BEGIN
	SELECT substring(extversion, '^\d+') INTO tsdb_version_major FROM pg_extension WHERE extname='timescaledb';

	IF (tsdb_version_major < 2)
	THEN
		SELECT (upper(ranges[1]) - lower(ranges[1])) INTO chunk_tm_interval FROM chunk_relation_size('%HISTTBL')
			ORDER BY ranges DESC LIMIT 1;

		IF NOT FOUND THEN
			chunk_tm_interval = 86400;
		END IF;

		PERFORM create_hypertable('%HISTTBL', 'clock', chunk_time_interval => chunk_tm_interval, migrate_data => true);
	ELSE
		PERFORM create_hypertable('%HISTTBL', 'clock', chunk_time_interval => (
			SELECT integer_interval FROM timescaledb_information.dimensions WHERE hypertable_name='%HISTTBL_old'
		), migrate_data => true);
	END IF;

	INSERT INTO %HISTTBL SELECT * FROM temp_%HISTTBL ON CONFLICT (itemid,clock,ns) DO NOTHING;

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

	if (not(defined $tsdb_compression))
	{
		$tsdb_out =~ s/%COMPRESS//g;
		$tsdb_out =~ s/%CONFIG_COMPR/UPDATE config SET compression_status=0;/g;
	}
	elsif ($tsdb_compression eq 'with_compression')
	{
		$tsdb_out =~ s/%COMPRESS/$tsdb_compress_sql/g;
		$tsdb_out =~ s/%CONFIG_COMPR/UPDATE config SET compression_status=1;/g;
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

	if ($db eq 'timescaledb')
	{
		die 'Table name should be provided to generate timescaledb per-table migration script' if (!$table);
		die 'Non-existent table name was provided' if (! grep { $_ eq $table } @tables);
	}
}

validate_args();

if ($db eq 'timescaledb')
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
	elsif ($db eq 'oracle')
	{
		foreach my $tbl (@tables)
		{
			output_table(\%oracle, $tbl, 1);
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
		foreach my $tbl (@tables)
		{
			output_tsdb($tbl);
		}
	}
}

