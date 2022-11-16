#!/usr/bin/env perl
#
# Zabbix
# Copyright (C) 2001-2022 Zabbix SIA
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License version 2 as
# published by the Free Software Foundation
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

use strict;
use warnings;

use File::Basename;

my $file = dirname($0) . "/../src/schema.tmpl";	# name the file

my ($state, %output, $eol, $fk_bol, $fk_eol, $ltab, $pkey, $table_name, $pkey_name);
my ($szcol1, $szcol2, $szcol3, $szcol4, $sequences, $sql_suffix, $triggers);
my ($fkeys, $fkeys_prefix, $fkeys_suffix, $uniq, $delete_cascade);

my %table_types;	# for making sure that table types aren't duplicated

my %c = (
	"type"		=>	"code",
	"database"	=>	"",
	"after"		=>	"\t{0}\n\n#undef ZBX_TYPE_LONGTEXT_LEN\n#undef ZBX_TYPE_SHORTTEXT_LEN\n\n};\n",
	"t_bigint"	=>	"ZBX_TYPE_UINT",
	"t_text"	=>	"ZBX_TYPE_TEXT",
	"t_double"	=>	"ZBX_TYPE_FLOAT",
	"t_id"		=>	"ZBX_TYPE_ID",
	"t_image"	=>	"ZBX_TYPE_BLOB",
	"t_integer"	=>	"ZBX_TYPE_INT",
	"t_longtext"	=>	"ZBX_TYPE_LONGTEXT",
	"t_nanosec"	=>	"ZBX_TYPE_INT",
	"t_serial"	=>	"ZBX_TYPE_UINT",
	"t_shorttext"	=>	"ZBX_TYPE_SHORTTEXT",
	"t_time"	=>	"ZBX_TYPE_INT",
	"t_varchar"	=>	"ZBX_TYPE_CHAR",
	"t_cuid"	=>	"ZBX_TYPE_CUID",
);

$c{"before"} = "/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include \"zbxdbschema.h\"
#include \"common.h\"

const ZBX_TABLE\ttables[] = {

#if defined(HAVE_ORACLE)
#	define ZBX_TYPE_SHORTTEXT_LEN	2048
#else
#	define ZBX_TYPE_SHORTTEXT_LEN	65535
#endif

#define ZBX_TYPE_LONGTEXT_LEN	0
#define ZBX_TYPE_TEXT_LEN	65535

";

my %mysql = (
	"type"		=>	"sql",
	"database"	=>	"mysql",
	"before"	=>	"",
	"after"		=>	"",
	"table_options"	=>	" ENGINE=InnoDB",
	"t_bigint"	=>	"bigint unsigned",
	"t_text"	=>	"text",
	"t_double"	=>	"DOUBLE PRECISION",
	"t_id"		=>	"bigint unsigned",
	"t_image"	=>	"longblob",
	"t_integer"	=>	"integer",
	"t_longtext"	=>	"longtext",
	"t_nanosec"	=>	"integer",
	"t_serial"	=>	"bigint unsigned",
	"t_shorttext"	=>	"text",
	"t_time"	=>	"integer",
	"t_varchar"	=>	"varchar",
	"t_cuid"	=>	"varchar(25)",
);

my %oracle = (
	"type"		=>	"sql",
	"database"	=>	"oracle",
	"before"	=>	"",
	"after"		=>	"",
	"table_options"	=>	"",
	"t_bigint"	=>	"number(20)",
	"t_text"	=>	"nclob",
	"t_double"	=>	"BINARY_DOUBLE",
	"t_id"		=>	"number(20)",
	"t_image"	=>	"blob",
	"t_integer"	=>	"number(10)",
	"t_longtext"	=>	"nclob",
	"t_nanosec"	=>	"number(10)",
	"t_serial"	=>	"number(20)",
	"t_shorttext"	=>	"nvarchar2(2048)",
	"t_time"	=>	"number(10)",
	"t_varchar"	=>	"nvarchar2",
	"t_cuid"	=>	"nvarchar2(25)",
);

my %postgresql = (
	"type"		=>	"sql",
	"database"	=>	"postgresql",
	"before"	=>	"",
	"after"		=>	"",
	"table_options"	=>	"",
	"t_bigint"	=>	"numeric(20)",
	"t_text"	=>	"text",
	"t_double"	=>	"DOUBLE PRECISION",
	"t_id"		=>	"bigint",
	"t_image"	=>	"bytea",
	"t_integer"	=>	"integer",
	"t_longtext"	=>	"text",
	"t_nanosec"	=>	"integer",
	"t_serial"	=>	"bigserial",
	"t_shorttext"	=>	"text",
	"t_time"	=>	"integer",
	"t_varchar"	=>	"varchar",
	"t_cuid"	=>	"varchar(25)",
);

my %sqlite3 = (
	"type"		=>	"sql",
	"database"	=>	"sqlite3",
	"before"	=>	"",
	"after"		=>	"",
	"table_options"	=>	"",
	"t_bigint"	=>	"bigint",
	"t_text"	=>	"text",
	"t_double"	=>	"DOUBLE PRECISION",
	"t_id"		=>	"bigint",
	"t_image"	=>	"longblob",
	"t_integer"	=>	"integer",
	"t_longtext"	=>	"text",
	"t_nanosec"	=>	"integer",
	"t_serial"	=>	"integer",
	"t_shorttext"	=>	"text",
	"t_time"	=>	"integer",
	"t_varchar"	=>	"varchar",
	"t_cuid"	=>	"varchar(25)",
);

sub rtrim($)
{
	my $string = shift;
	$string =~ s/(\r|\n)+$// if ($string);
	return $string;
}

sub newstate($)
{
	my $new = shift;

	if ($state eq "field")
	{
		if ($output{"type"} eq "sql" && ($new eq "index" || $new eq "table" || $new eq "row"))
		{
			print "${pkey}${eol}\n)$output{'table_options'};${eol}\n";
		}
		if ($new eq "field")
		{
			print ",${eol}\n";
		}
	}

	if ($state ne "bof")
	{
		if ($output{"type"} eq "code" && $new eq "table")
		{
			if ($uniq ne "")
			{
				print ",\n\t\t{0}\n\t\t}${uniq}\n\t},\n";
				$uniq = "";
			}
			else
			{
				print ",\n\t\t{0}\n\t\t},\n\t\tNULL\n\t},\n";
			}
		}
	}

	$state = $new;
}

sub process_table($)
{
	my $line = shift;
	my $flags;

	newstate("table");

	$delete_cascade = 0;

	($table_name, $pkey_name, $flags) = split(/\|/, $line, 3);

	if ($output{"type"} eq "code")
	{
		if ($flags eq "")
		{
			$flags = "0";
		}

		for ($flags)
		{
			# do not output ZBX_DATA ZBX_DASHBOARD and ZBX_TEMPLATE, remove it
			s/ZBX_DATA//;
			s/ZBX_TEMPLATE//;
			s/ZBX_DASHBOARD//;
			s/,+$//;
			s/^,+//;
			s/,+/ \| /g;
			s/^$/0/;
		}

		print "\t{\"${table_name}\",\t\"${pkey_name}\",\t${flags},\n\t\t{\n";
	}
	else
	{
		if ($pkey_name ne "")
		{
			$pkey = ",${eol}\n${ltab}PRIMARY KEY (${pkey_name})";
		}
		else
		{
			$pkey = "";
		}

		if ($output{"database"} eq "mysql")
		{
			print "CREATE TABLE `${table_name}` (${eol}\n";
		}
		else
		{
			print "CREATE TABLE ${table_name} (${eol}\n";
		}
	}
}

sub process_field($)
{
	my $line = shift;

	newstate("field");

	my ($name, $type, $default, $null, $flags, $relN, $fk_table, $fk_field, $fk_flags) = split(/\|/, $line, 9);
	my ($type_short, $length) = $type =~ /^(\w+)(?:\((\d+)\))?$/;

	if ($output{"type"} eq "code")
	{
		$type = $output{$type_short};
		if ($type eq "ZBX_TYPE_CHAR")
		{
			# use specified $length, don't override it
		}
		elsif ($type eq "ZBX_TYPE_TEXT")
		{
			$length = "ZBX_TYPE_TEXT_LEN";
		}
		elsif ($type eq "ZBX_TYPE_SHORTTEXT")
		{
			$length = "ZBX_TYPE_SHORTTEXT_LEN";
		}
		elsif ($type eq "ZBX_TYPE_LONGTEXT")
		{
			$length = "ZBX_TYPE_LONGTEXT_LEN";
		}
		elsif ($type eq "ZBX_TYPE_CUID")
		{
			$length = 0;
		}
		else
		{
			$length = 0;
		}

		for ($flags)
		{
			# do not output ZBX_NODATA, remove it
			s/ZBX_NODATA//;
			s/,+$//;
			s/^,+//;
			s/,+/ \| /g;
			s/^$/0/;
		}

		if ($null eq "NOT NULL")
		{
			if ($flags ne "0")
			{
				$flags = "ZBX_NOTNULL | ${flags}";
			}
			else
			{
				$flags = "ZBX_NOTNULL";
			}
		}

		$flags =~ s/,/ \| /g;

		if ($fk_table)
		{
			if (not $fk_field or $fk_field eq "")
			{
				$fk_field = $name;
			}

			$fk_table = "\"${fk_table}\"";
			$fk_field = "\"${fk_field}\"";

			if (not $fk_flags or $fk_flags eq "")
			{
				$delete_cascade = 1;
				$fk_flags = "ZBX_FK_CASCADE_DELETE";
			}
			elsif ($fk_flags eq "RESTRICT")
			{
				$fk_flags = "0";
			}
		}
		else
		{
			$fk_table = "NULL";
			$fk_field = "NULL";
			$fk_flags = "0";
		}

		if ($default eq "")
		{
			$default = "NULL";
		}
		else
		{
			$default =~ s/'//g;
			$default = "\"$default\"";
		}

		print "\t\t{\"${name}\",\t${default},\t${fk_table},\t${fk_field},\t${length},\t$type,\t${flags},\t${fk_flags}}";
	}
	else
	{
		my @text_fields;

		$type =~ s/$type_short/$output{$type_short}/g;

		if (($output{"database"} eq "oracle") && (index($type, "nvarchar2") == 0 || index($type, "nclob") == 0))
		{
			$null = "";
		}

		my $row = $null;

		if ($type_short eq "t_serial")
		{
			if ($output{"database"} eq "sqlite3")
			{
				$row = sprintf("%-*s PRIMARY KEY AUTOINCREMENT", $szcol4, $row);
				$pkey = "";
			}
			elsif ($output{"database"} eq "mysql")
			{
				$row = sprintf("%-*s auto_increment", $szcol4, $row);
			}
			elsif ($output{"database"} eq "oracle")
			{
				$sequences .= "CREATE SEQUENCE ${table_name}_seq${eol}\n";
				$sequences .= "START WITH 1${eol}\n";
				$sequences .= "INCREMENT BY 1${eol}\n";
				$sequences .= "NOMAXVALUE${eol}\n/${eol}\n";
				$sequences .= "CREATE TRIGGER ${table_name}_tr${eol}\n";
				$sequences .= "BEFORE INSERT ON ${table_name}${eol}\n";
				$sequences .= "FOR EACH ROW${eol}\n";
				$sequences .= "BEGIN${eol}\n";
				$sequences .= "SELECT ${table_name}_seq.nextval INTO :new.${name} FROM dual;${eol}\n";
				$sequences .= "END;${eol}\n/${eol}\n";
			}
		}

		my $references = "";

		if ($relN and $relN ne "" and $relN ne "-")
		{
			my $only = "";

			if (not $fk_field or $fk_field eq "")
			{
				$fk_field = $name;
			}

			# RESTRICT may contain new line chars, we need to clean them out
			$fk_flags = rtrim($fk_flags);

			if (not $fk_flags or $fk_flags eq "")
			{
				$delete_cascade = 1;
				$fk_flags = " ON DELETE CASCADE";
			}
			elsif ($fk_flags eq "RESTRICT")
			{
				$fk_flags = "";
			}

			if ($output{"database"} eq "postgresql")
			{
				$only = " ONLY";
			}

			my $cname = "c_${table_name}_${relN}";

			if ($output{"database"} eq "sqlite3")
			{
				$references = " REFERENCES ${fk_table} (${fk_field})${fk_flags}";
			}
			else
			{
				$references = "";

				if ($output{"database"} eq "mysql")
				{
					$fkeys .= "${fk_bol}ALTER TABLE${only} `${table_name}` ADD CONSTRAINT `${cname}` FOREIGN KEY (`${name}`) REFERENCES `${fk_table}` (`${fk_field}`)${fk_flags}${fk_eol}\n";
				}
				else
				{
					$fkeys .= "${fk_bol}ALTER TABLE${only} ${table_name} ADD CONSTRAINT ${cname} FOREIGN KEY (${name}) REFERENCES ${fk_table} (${fk_field})${fk_flags}${fk_eol}\n";
				}
			}
		}

		if ($output{"database"} eq "mysql")
		{
			@text_fields = ('blob', 'longblob', 'text', 'longtext');
			$default = "" if (grep /$output{$type_short}/, @text_fields);

			$name = "`${name}`";
		}

		if ($default ne "")
		{
			$default = "DEFAULT $default";
		}

		printf("${ltab}%-*s %-*s %-*s ${row}${references}", $szcol1, $name, $szcol2, $type, $szcol3, $default);
	}
}

sub process_index($$)
{
	my $line   = shift;
	my $unique = shift;

	newstate("index");

	my ($name, $fields) = split(/\|/, $line, 2);

	if ($output{"type"} eq "code")
	{
		if (1 == $unique)
		{
			$uniq = ",\n\t\t\"${fields}\"";
		}
	}
	else
	{
		if (1 == $unique)
		{
			$unique = " UNIQUE";
		}
		else
		{
			$unique = "";
		}

		if ($output{"database"} eq "mysql")
		{
			$fields =~ s/,/`,`/g;

			my $quote_index = "`$fields`";
			$quote_index =~ s/\)`/\)/g;
			$quote_index =~ s/\(/`\(/g;

			print "CREATE${unique} INDEX `${table_name}_$name` ON `$table_name` ($quote_index);${eol}\n";
		}
		else
		{
			$fields =~ s/\(\d+\)//g;

			print "CREATE${unique} INDEX ${table_name}_$name ON $table_name ($fields);${eol}\n";
		}
	}
}

sub process_row($)
{
	my $line = shift;

	newstate("row");

	my @array = split(/\|/, $line);

	my $first = 1;
	my $values = "(";

	foreach (@array)
	{
		$values .= "," if ($first == 0);
		$first = 0;

		# remove leading and trailing spaces
		$_ =~ s/^\s+//;
		$_ =~ s/\s+$//;

		if ($_ eq 'NULL')
		{
			$values .= $_;
		}
		else
		{
			my $modifier = '';

			# escape backslashes
			if (/\\/)
			{
				if ($output{'database'} eq 'postgresql')
				{
					$_ =~ s/\\/\\\\/g;
					$modifier = 'E';
				}
				elsif ($output{'database'} eq 'mysql')
				{
					$_ =~ s/\\/\\\\/g;
				}
			}

			# escape single quotes
			if (/'/)
			{
				if ($output{'database'} eq 'mysql')
				{
					$_ =~ s/'/\\'/g;
				}
				else
				{
					$_ =~ s/'/''/g;
				}
			}

			$_ =~ s/&pipe;/|/g;

			if ($output{'database'} eq 'mysql' || $output{'database'} eq 'oracle')
			{
				$_ =~ s/&eol;/\\r\\n/g;
			}
			else
			{
				$_ =~ s/&eol;/\x0D\x0A/g;
			}

			$values .= "${modifier}'${_}'";
		}
	}

	$values .= ")";

	print "INSERT INTO $table_name VALUES $values;${eol}\n";
}

sub timescaledb()
{
	print<<EOF
DO \$\$
DECLARE
	minimum_postgres_version_major		INTEGER;
	minimum_postgres_version_minor		INTEGER;
	current_postgres_version_major		INTEGER;
	current_postgres_version_minor		INTEGER;
	current_postgres_version_full		VARCHAR;

	minimum_timescaledb_version_major	INTEGER;
	minimum_timescaledb_version_minor	INTEGER;
	current_timescaledb_version_major	INTEGER;
	current_timescaledb_version_minor	INTEGER;
	current_timescaledb_version_full	VARCHAR;
BEGIN
	SELECT 10 INTO minimum_postgres_version_major;
	SELECT 2 INTO minimum_postgres_version_minor;
	SELECT 1 INTO minimum_timescaledb_version_major;
	SELECT 5 INTO minimum_timescaledb_version_minor;

	SHOW server_version INTO current_postgres_version_full;

	IF NOT found THEN
		RAISE EXCEPTION 'Cannot determine PostgreSQL version, aborting';
	END IF;

	SELECT substring(current_postgres_version_full, '^(\\d+).') INTO current_postgres_version_major;
	SELECT substring(current_postgres_version_full, '^\\d+.(\\d+)') INTO current_postgres_version_minor;

	IF (current_postgres_version_major < minimum_postgres_version_major OR
			(current_postgres_version_major = minimum_postgres_version_major AND
			current_postgres_version_minor < minimum_postgres_version_minor)) THEN
			RAISE EXCEPTION 'PostgreSQL version % is NOT SUPPORTED (with TimescaleDB)! Minimum is %.%.0 !',
					current_postgres_version_full, minimum_postgres_version_major,
					minimum_postgres_version_minor;
	ELSE
		RAISE NOTICE 'PostgreSQL version % is valid', current_postgres_version_full;
	END IF;

	SELECT extversion INTO current_timescaledb_version_full FROM pg_extension WHERE extname = 'timescaledb';

	IF NOT found THEN
		RAISE EXCEPTION 'TimescaleDB extension is not installed';
	ELSE
		RAISE NOTICE 'TimescaleDB extension is detected';
	END IF;

	SELECT substring(current_timescaledb_version_full, '^(\\d+).') INTO current_timescaledb_version_major;
	SELECT substring(current_timescaledb_version_full, '^\\d+.(\\d+)') INTO current_timescaledb_version_minor;

	IF (current_timescaledb_version_major < minimum_timescaledb_version_major OR
			(current_timescaledb_version_major = minimum_timescaledb_version_major AND
			current_timescaledb_version_minor < minimum_timescaledb_version_minor)) THEN
		RAISE EXCEPTION 'TimescaleDB version % is UNSUPPORTED! Minimum is %.%.0!',
				current_timescaledb_version_full, minimum_timescaledb_version_major,
				minimum_timescaledb_version_minor;
	ELSE
		RAISE NOTICE 'TimescaleDB version % is valid', current_timescaledb_version_full;
	END IF;
EOF
	;

	for ("history", "history_uint", "history_log", "history_text", "history_str")
	{
		print<<EOF
	PERFORM create_hypertable('$_', 'clock', chunk_time_interval => 86400, migrate_data => true);
EOF
	;
	}

	for ("trends", "trends_uint")
	{
		print<<EOF
	PERFORM create_hypertable('$_', 'clock', chunk_time_interval => 2592000, migrate_data => true);
EOF
	;
	}
	print<<EOF
	UPDATE config SET db_extension='timescaledb',hk_history_global=1,hk_trends_global=1;
	UPDATE config SET compression_status=1,compress_older='7d';
	RAISE NOTICE 'TimescaleDB is configured successfully';
END \$\$;
EOF
	;
	exit;
}

sub usage()
{
	print "Usage: $0 [c|mysql|oracle|postgresql|sqlite3|timescaledb]\n";
	print "The script generates Zabbix SQL schemas and C code for different database engines.\n";
	exit;
}

sub unix_timestamp()
{
	if ($output{"database"} eq "mysql")
	{
		return "unix_timestamp()";
	}
	if ($output{"database"} eq "oracle")
	{
		return "(cast(sys_extract_utc(systimestamp) as date)-date'1970-01-01')*86400";
	}
	if ($output{"database"} eq "postgresql")
	{
		return "cast(extract(epoch from now()) as int)";
	}
	if ($output{"database"} eq "sqlite3")
	{
		return "cast(strftime('%s', 'now') as integer)";
	}
}

sub open_trigger($)
{
	my $type = shift;
	my $out;

	$out = "create trigger ${table_name}_${type} ";
	if ($type eq "insert")
	{
		$out .= "after insert";
	}
	elsif ($type eq "update")
	{
		$out .= "after update";
	}
	elsif ($type eq "delete")
	{
		$out .= "before delete";
	}

	$out .= " on ${table_name}${eol}\n";
	$out .= "for each row${eol}\n";

	if ($output{"database"} eq "mysql")
	{
		$out .= "insert into changelog (object,objectid,operation,clock)${eol}\n";
	}
	elsif ($output{"database"} eq "oracle"  || $output{"database"} eq "sqlite3")
	{
		$out .= "begin${eol}\n";
		$out .= "insert into changelog (object,objectid,operation,clock)${eol}\n";
	}
	elsif ($output{"database"} eq "postgresql")
	{
		$out .= "execute procedure changelog_${table_name}_${type}();${eol}\n";
	}

	return $out;
}

sub close_trigger()
{
	if ($output{"database"} eq "mysql")
	{
		return "\$\$${eol}\n";
	}
	elsif ($output{"database"} eq "postgresql")
	{
		return "";
	}
	elsif ($output{"database"} eq "oracle")
	{
		return "end;${eol}\n/${eol}\n";
	}
	elsif ($output{"database"} eq "sqlite3")
	{
		return "end;${eol}\n";
	}
}

sub open_function($)
{
	my $type = shift;
	my $out;

	$out = "create or replace function changelog_${table_name}_${type}() returns trigger as \$\$${eol}\n";
	$out .= "begin${eol}\n";
	$out .= "insert into changelog (object,objectid,operation,clock)${eol}\n";

	return $out;
}

sub close_function($)
{
	my $type = shift;
	my ($out, $ret_row);
	
	if ($type eq "delete")
	{
		$ret_row = "old";
	}
	else
	{
		$ret_row = "new";
	}

	$out = "return ${ret_row};${eol}\n";
	$out .= "end;${eol}\n";
	$out .= "\$\$ language plpgsql;${eol}\n";

	return $out;
}

sub process_changelog($)
{
	my $table_type = shift;

	if ($delete_cascade)
	{
		die("foreign keys without RESTRICT flag are not compatible with table CHANGELOG token");
	}

	if (exists($table_types{$table_type}) && $table_types{$table_type} ne $table_name)
	{
		die("cannot use table type '$table_type' for table '$table_name', it was already used for table '$table_types{$table_type}'");
	}
	$table_types{$table_type} = $table_name;

	my $unix_timestamp = unix_timestamp();

	if ($output{"database"} eq "c")
	{
		return;
	}
	elsif ($output{"database"} eq "mysql" || $output{"database"} eq "sqlite3")
	{
		$triggers .= open_trigger('insert');
		$triggers .= "values (${table_type},new.${pkey_name},1,${unix_timestamp});${eol}\n";
		$triggers .= close_trigger();

		$triggers .= open_trigger('update');
		$triggers .= "values (${table_type},old.${pkey_name},2,${unix_timestamp});${eol}\n";
		$triggers .= close_trigger();

		$triggers .= open_trigger('delete');
		$triggers .= "values (${table_type},old.${pkey_name},3,${unix_timestamp});${eol}\n";
		$triggers .= close_trigger();
	}
	elsif ($output{"database"} eq "postgresql")
	{
		$triggers .= open_function('insert');
		$triggers .= "values (${table_type},new.${pkey_name},1,${unix_timestamp});${eol}\n";
		$triggers .= close_function('insert');
		$triggers .= open_trigger('insert');
		$triggers .= close_trigger();

		$triggers .= open_function('update');
		$triggers .= "values (${table_type},old.${pkey_name},2,${unix_timestamp});${eol}\n";
		$triggers .= close_function('update');
		$triggers .= open_trigger('update');
		$triggers .= close_trigger();

		$triggers .= open_function('delete');
		$triggers .= "values (${table_type},old.${pkey_name},3,${unix_timestamp});${eol}\n";
		$triggers .= close_function('delete');
		$triggers .= open_trigger('delete');
		$triggers .= close_trigger();
	}
	elsif ($output{"database"} eq "oracle")
	{
		$triggers .= open_trigger('insert');
		$triggers .= "values (${table_type},:new.${pkey_name},1,${unix_timestamp});${eol}\n";
		$triggers .= close_trigger();

		$triggers .= open_trigger('update');
		$triggers .= "values (${table_type},:old.${pkey_name},2,${unix_timestamp});${eol}\n";
		$triggers .= close_trigger();

		$triggers .= open_trigger('delete');
		$triggers .= "values (${table_type},:old.${pkey_name},3,${unix_timestamp});${eol}\n";
		$triggers .= close_trigger();
	}
}

sub process_update_trigger_function($)
{
	my $line = shift;
	my $out = "";

	if ($output{"database"} eq "c" || $output{"database"} eq "sqlite3")
	{
		return;
	}

	my ($original_column_name, $indexed_column_name, $idname, $func_name) = split(/\|/, $line, 4);

	if ($output{"database"} eq "oracle")
	{
		$out .= "create trigger ${table_name}_${indexed_column_name}_insert${eol}\n";
		$out .= "before insert on ${table_name} for each row${eol}\n";
		$out .= "begin${eol}\n";
		$out .=		":new.${indexed_column_name}:=${func_name}(:new.${original_column_name});${eol}\n";
		$out .= "end;${eol}\n/${eol}\n";

		$out .= "create trigger ${table_name}_${indexed_column_name}_update${eol}\n";
		$out .= "before update on ${table_name} for each row${eol}\n";
		$out .= "begin${eol}\n";
		$out .= 	"if :new.${original_column_name}<>:old.${original_column_name}${eol}\n";
		$out .= 	"then${eol}\n";
		$out .= 		":new.${indexed_column_name}:=${func_name}(:new.${original_column_name});${eol}\n";
		$out .=		"end if;${eol}\n";
		$out .= "end;${eol}\n/${eol}\n";
	}
	elsif ($output{"database"} eq "mysql")
	{
		$out .= "create trigger ${table_name}_${indexed_column_name}_insert${eol}\n";
		$out .= "before insert on ${table_name} for each row${eol}\n";
		$out .= "set new.${indexed_column_name}=${func_name}(new.${original_column_name})${eol}\n";
		$out .= "\$\$${eol}\n";

		$out .= "create trigger ${table_name}_${indexed_column_name}_update${eol}\n";
		$out .= "before update on ${table_name} for each row${eol}\n";
		$out .= "begin${eol}\n";
		$out .= 	"if new.${original_column_name}<>old.${original_column_name}${eol}\n";
		$out .= 	"then${eol}\n";
		$out .= 		"set new.${indexed_column_name}=${func_name}(new.${original_column_name});${eol}\n";
		$out .= 	"end if;${eol}\n";
		$out .= "end;\$\$${eol}\n";
	}
	elsif ($output{"database"} eq "postgresql")
	{
		$out .= "";
		$out .= "create or replace function ${table_name}_${indexed_column_name}_${func_name}()${eol}\n";
		$out .= "returns trigger language plpgsql as \$func\$${eol}\n";
		$out .= "begin${eol}\n";
		$out .=		"update ${table_name} set ${indexed_column_name}=${func_name}(${original_column_name})${eol}\n";
		$out .= 	"where ${idname}=new.${idname};${eol}\n";
		$out .= 	"return null;${eol}\n";
		$out .= "end \$func\$;${eol}\n";

		$out .= "create trigger ${table_name}_${indexed_column_name}_insert after insert ${eol}\n";
		$out .= "on ${table_name} ${eol}\n";
		$out .= "for each row execute function ${table_name}_${indexed_column_name}_${func_name}();${eol}\n";
		$out .= "create trigger ${table_name}_${indexed_column_name}_update after update ${eol}\n";
		$out .= "of ${original_column_name} on ${table_name} ${eol}\n";
		$out .= "for each row execute function ${table_name}_${indexed_column_name}_${func_name}();${eol}\n";
	}
	$triggers .= $out;
}

sub process()
{
	print $output{"before"};

	$state = "bof";
	$fkeys = "";
	$sequences = "";
	$triggers = "";
	$uniq = "";

	open(INFO, $file);	# open the file
	my @lines = <INFO>;	# read it into an array
	close(INFO);		# close the file

	foreach my $line (@lines)
	{
		$line =~ tr/\t//d;
		chop($line);

		my ($type, $opts) = split(/\|/, $line, 2);

		if ($type)
		{
			if ($type eq 'FIELD')					{ process_field($opts); }
			elsif ($type eq 'INDEX')				{ process_index($opts, 0); }
			elsif ($type eq 'TABLE')				{ process_table($opts); }
			elsif ($type eq 'UNIQUE')				{ process_index($opts, 1); }
			elsif ($type eq 'CHANGELOG')				{ process_changelog($opts); }
			elsif ($type eq 'UPD_TRIG_FUNC')
			{
				process_update_trigger_function($opts);
			}
			elsif ($type eq 'ROW' && $output{"type"} ne "code")	{ process_row($opts); }
		}
	}

	newstate("table");

	if ($output{"database"} eq "mysql")
	{
		print "DELIMITER \$\$${eol}\n";
	}

	print $sequences . $triggers . $sql_suffix;

	if ($output{"database"} eq "mysql")
	{
		print "DELIMITER ;${eol}\n";
	}

	print $fkeys_prefix . $fkeys . $fkeys_suffix;
	print $output{"after"};
}

sub c_append_changelog_tables()
{
	print "\nconst zbx_db_table_changelog_t\tchangelog_tables[] = {\n";
	
	while (my ($object, $table) = each(%table_types)) {
		print "\t{\"$table\", $object},\n"
	}
	
	print	"\t{0}\n};\n";
}

sub main()
{
	if ($#ARGV != 0)
	{
		usage();
	}

	$eol = "";
	$fk_bol = "";
	$fk_eol = ";";
	$ltab = "\t";
	$szcol1 = 24;
	$szcol2 = 15;
	$szcol3 = 25;
	$szcol4 = 7;
	$sql_suffix = "";
	$fkeys_prefix = "";
	$fkeys_suffix = "";

	my $format = $ARGV[0];

	if ($format eq 'c')			{ %output = %c; }
	elsif ($format eq 'mysql')		{ %output = %mysql; }
	elsif ($format eq 'oracle')		{ %output = %oracle; }
	elsif ($format eq 'postgresql')		{ %output = %postgresql; }
	elsif ($format eq 'sqlite3')		{ %output = %sqlite3; }
	elsif ($format eq 'timescaledb')	{ timescaledb(); }
	else					{ usage(); }

	process();

	if ($format eq "c")
	{
		c_append_changelog_tables();
		
		$eol = "\\n\\";
		$fk_bol = "\t\"";
		$fk_eol = "\",";
		$ltab = "";
		$szcol1 = 0;
		$szcol2 = 0;
		$szcol3 = 0;
		$szcol4 = 0;
		$sql_suffix="\";\n";
		$fkeys_prefix = "const char\t*const db_schema_fkeys[] = {\n";
		$fkeys_suffix = "\tNULL\n};\n";

		print "#if defined(HAVE_SQLITE3)\nconst char\t*const db_schema = \"\\\n";
		%output = %sqlite3;
		process();
		print "#else\t/* HAVE_SQLITE3 */\n";
		print "const char\t*const db_schema = NULL;\n";
		print "#endif\t/* not HAVE_SQLITE3 */\n";
	}
}

main();
