#!/usr/bin/perl -w
#
# Zabbix
# Copyright (C) 2001-2014 Zabbix SIA
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
use File::Basename;

my $file = dirname($0)."/../src/schema.tmpl";	# name the file

my ($state, %output, $eol, $fk_bol, $fk_eol, $ltab, $pkey, $table_name);
my ($szcol1, $szcol2, $szcol3, $szcol4, $sequences, $sql_suffix);
my ($fkeys, $fkeys_prefix, $fkeys_suffix, $fkeys_drop, $uniq);

my %c = (
	"type"		=>	"code",
	"database"	=>	"",
	"after"		=>	"\t{0}\n};\n",
	"t_bigint"	=>	"ZBX_TYPE_UINT",
	"t_char"	=>	"ZBX_TYPE_CHAR",
	"t_cksum_text"	=>	"ZBX_TYPE_TEXT",
	"t_double"	=>	"ZBX_TYPE_FLOAT",
	"t_history_log"	=>	"ZBX_TYPE_TEXT",
	"t_history_text"=>	"ZBX_TYPE_TEXT",
	"t_id"		=>	"ZBX_TYPE_ID",
	"t_image"	=>	"ZBX_TYPE_BLOB",
	"t_integer"	=>	"ZBX_TYPE_INT",
	"t_longtext"	=>	"ZBX_TYPE_TEXT",
	"t_nanosec"	=>	"ZBX_TYPE_INT",
	"t_serial"	=>	"ZBX_TYPE_UINT",
	"t_text"	=>	"ZBX_TYPE_TEXT",
	"t_time"	=>	"ZBX_TYPE_INT",
	"t_varchar"	=>	"ZBX_TYPE_CHAR"
);

$c{"before"} = "/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include \"common.h\"
#include \"dbschema.h\"

const ZBX_TABLE\ttables[] = {
";

my %ibm_db2 = (
	"type"		=>	"sql",
	"database"	=>	"ibm_db2",
	"before"	=>	"",
	"after"		=>	"",
	"table_options"	=>	"",
	"t_bigint"	=>	"bigint",
	"t_char"	=>	"varchar",
	"t_cksum_text"	=>	"varchar(2048)",
	"t_double"	=>	"decfloat(16)",
	"t_history_log"	=>	"varchar(2048)",
	"t_history_text"=>	"varchar(2048)",
	"t_id"		=>	"bigint",
	"t_image"	=>	"blob",
	"t_integer"	=>	"integer",
	"t_longtext"	=>	"varchar(2048)",
	"t_nanosec"	=>	"integer",
	"t_serial"	=>	"bigint",
	"t_text"	=>	"varchar(2048)",
	"t_time"	=>	"integer",
	"t_varchar"	=>	"varchar"
);

my %mysql = (
	"type"		=>	"sql",
	"database"	=>	"mysql",
	"before"	=>	"",
	"after"		=>	"",
	"table_options"	=>	" ENGINE=InnoDB",
	"t_bigint"	=>	"bigint unsigned",
	"t_char"	=>	"char",
	"t_cksum_text"	=>	"text",
	"t_double"	=>	"double(16,4)",
	"t_history_log"	=>	"text",
	"t_history_text"=>	"text",
	"t_id"		=>	"bigint unsigned",
	"t_image"	=>	"longblob",
	"t_integer"	=>	"integer",
	"t_longtext"	=>	"longtext",
	"t_nanosec"	=>	"integer",
	"t_serial"	=>	"bigint unsigned",
	"t_text"	=>	"text",
	"t_time"	=>	"integer",
	"t_varchar"	=>	"varchar"
);

my %oracle = (
	"type"		=>	"sql",
	"database"	=>	"oracle",
	"before"	=>	"",
	"after"		=>	"",
	"table_options"	=>	"",
	"t_bigint"	=>	"number(20)",
	"t_char"	=>	"nvarchar2",
	"t_cksum_text"	=>	"nclob",
	"t_double"	=>	"number(20,4)",
	"t_history_log"	=>	"nclob",
	"t_history_text"=>	"nclob",
	"t_id"		=>	"number(20)",
	"t_image"	=>	"blob",
	"t_integer"	=>	"number(10)",
	"t_longtext"	=>	"nclob",
	"t_nanosec"	=>	"number(10)",
	"t_serial"	=>	"number(20)",
	"t_text"	=>	"nvarchar2(2048)",
	"t_time"	=>	"number(10)",
	"t_varchar"	=>	"nvarchar2"
);

my %postgresql = (
	"type"		=>	"sql",
	"database"	=>	"postgresql",
	"before"	=>	"",
	"after"		=>	"",
	"table_options"	=>	"",
	"t_bigint"	=>	"numeric(20)",
	"t_char"	=>	"char",
	"t_cksum_text"	=>	"text",
	"t_double"	=>	"numeric(16,4)",
	"t_history_log"	=>	"text",
	"t_history_text"=>	"text",
	"t_id"		=>	"bigint",
	"t_image"	=>	"bytea",
	"t_integer"	=>	"integer",
	"t_longtext"	=>	"text",
	"t_nanosec"	=>	"integer",
	"t_serial"	=>	"serial",
	"t_text"	=>	"text",
	"t_time"	=>	"integer",
	"t_varchar"	=>	"varchar"
);

my %sqlite3 = (
	"type"		=>	"sql",
	"database"	=>	"sqlite3",
	"before"	=>	"",
	"after"		=>	"",
	"table_options"	=>	"",
	"t_bigint"	=>	"bigint",
	"t_char"	=>	"char",
	"t_cksum_text"	=>	"text",
	"t_double"	=>	"double(16,4)",
	"t_history_log"	=>	"text",
	"t_history_text"=>	"text",
	"t_id"		=>	"bigint",
	"t_image"	=>	"longblob",
	"t_integer"	=>	"integer",
	"t_longtext"	=>	"text",
	"t_nanosec"	=>	"integer",
	"t_serial"	=>	"integer",
	"t_text"	=>	"text",
	"t_time"	=>	"integer",
	"t_varchar"	=>	"varchar"
);

sub rtrim($)
{
	my $string = shift;
	$string =~ s/(\r|\n)+$// if ($string);
	return $string;
}

sub newstate
{
	my $new = $_[0];

	if ($state eq "field")
	{
		if ($output{"type"} eq "sql" && $new eq "index") { print "${pkey}${eol}\n)$output{'table_options'};${eol}\n"; }
		if ($output{"type"} eq "sql" && $new eq "table") { print "${pkey}${eol}\n)$output{'table_options'};${eol}\n"; }
		if ($new eq "field") { print ",${eol}\n"; }
	}

	if ($state ne "bof")
	{
		if ($output{"type"} eq "code" && $new eq "table") { print ",\n\t\t{0}\n\t\t}${uniq}\n\t},\n"; $uniq = ""; }
	}

	$state = $new;
}

sub process_table
{
	my $line = $_[0];
	my $flags;

	newstate("table");

	($table_name, $pkey, $flags) = split(/\|/, $line, 3);

	if ($output{"type"} eq "code")
	{
		if ($flags eq "")
		{
			$flags = "0";
		}

		for ($flags)
		{
			# do not output ZBX_DATA, remove it
			s/ZBX_DATA//;
			s/,+$//;
			s/^,+//;
			s/,+/ \| /g;
			s/^$/0/;
		}

		print "\t{\"${table_name}\",\t\"${pkey}\",\t${flags},\n\t\t{\n";
	}
	else
	{
		if ($pkey ne "")
		{
			$pkey = ",${eol}\n${ltab}PRIMARY KEY (${pkey})";
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

sub process_field
{
	my $line = $_[0];
	my $type_2;

	newstate("field");

	my $name = "";
	my $type = "";
	my $default = "";
	my $null = "";
	my $flags = "";
	my $relN = "";
	my $fk_table = "";
	my $fk_field = "";
	my $fk_flags = "";

	($name, $type, $default, $null, $flags, $relN, $fk_table, $fk_field, $fk_flags) = split(/\|/, $line, 9);
	my ($type_short, $length) = split(/\(/, $type, 2);

	if ($output{"type"} eq "code")
	{
		$type = $output{$type_short};

		if ($type eq "ZBX_TYPE_CHAR")
		{
			for ($length)
			{
				s/\)//;
			}
		}
		elsif ($type eq "ZBX_TYPE_TEXT")
		{
			$length = 65535;
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

		for ($flags)
		{
			s/,/ \| /g;
		}

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

		print "\t\t{\"${name}\",\t${fk_table},\t${fk_field},\t${length},\t$type,\t${flags},\t${fk_flags}}";
	}
	else
	{
		my @text_fields;
		$a = $output{$type_short};
		$_ = $type;
		s/$type_short/$a/g;
		$type_2 = $_;

		if (($output{"database"} eq "oracle") && (0 == index($type_2, "nvarchar2") || 0 == index($type_2, "nclob")))
		{
			$null = "";
		}

		my $row = $null;

		if ($type eq "t_serial")
		{
			if ($output{"database"} eq "sqlite3")
			{
				$row = sprintf("%-*s PRIMARY KEY AUTOINCREMENT", $szcol4, $row);
				$pkey = "";
			}
			elsif ($output{"database"} eq "mysql")
			{
				$row = sprintf("%-*s auto_increment unique", $szcol4, $row);
			}
			elsif ($output{"database"} eq "oracle")
			{
				$sequences = "${sequences}CREATE SEQUENCE ${table_name}_seq${eol}\n";
				$sequences = "${sequences}START WITH 1${eol}\n";
				$sequences = "${sequences}INCREMENT BY 1${eol}\n";
				$sequences = "${sequences}NOMAXVALUE${eol}\n/${eol}\n";
				$sequences = "${sequences}CREATE TRIGGER ${table_name}_tr${eol}\n";
				$sequences = "${sequences}BEFORE INSERT ON ${table_name}${eol}\n";
				$sequences = "${sequences}FOR EACH ROW${eol}\n";
				$sequences = "${sequences}BEGIN${eol}\n";
				$sequences = "${sequences}SELECT ${table_name}_seq.nextval INTO :new.id FROM dual;${eol}\n";
				$sequences = "${sequences}END;${eol}\n/${eol}\n";
			}
			elsif ($output{"database"} eq "ibm_db2")
			{
				$row = "$row\tGENERATED ALWAYS AS IDENTITY (START WITH 1 INCREMENT BY 1)";
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

# RESTRICT may contains new line chars we need to clean them out
			$fk_flags = rtrim($fk_flags);

			if (not $fk_flags or $fk_flags eq "")
			{
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
					$fkeys = "${fkeys}${fk_bol}ALTER TABLE${only} `${table_name}` ADD CONSTRAINT `${cname}` FOREIGN KEY (`${name}`) REFERENCES `${fk_table}` (`${fk_field}`)${fk_flags}${fk_eol}\n";
					$fkeys_drop = "${fkeys_drop}${fk_bol}ALTER TABLE${only} `${table_name}` DROP FOREIGN KEY `${cname}`${fk_eol}\n";
				}
				else
				{
					$fkeys = "${fkeys}${fk_bol}ALTER TABLE${only} ${table_name} ADD CONSTRAINT ${cname} FOREIGN KEY (${name}) REFERENCES ${fk_table} (${fk_field})${fk_flags}${fk_eol}\n";
					$fkeys_drop = "${fkeys_drop}${fk_bol}ALTER TABLE${only} ${table_name} DROP CONSTRAINT ${cname}${fk_eol}\n";
				}
			}
		}

		if ($default ne "")
		{
			if ($output{"database"} eq "ibm_db2")
			{
				$default = "WITH DEFAULT $default";
			}
			else
			{
				$default = "DEFAULT $default";
			}
		}

		if ($output{"database"} eq "mysql")
		{
			@text_fields = ('blob', 'longblob', 'text', 'longtext');

			if (grep /$output{$type_short}/, @text_fields)
			{
				$default = "";
			}

			$name = "`${name}`";
		}

		if ($output{"database"} eq "ibm_db2")
		{
			@text_fields = ('blob');

			if (grep /$output{$type_short}/, @text_fields)
			{
				$default = "";
			}
		}

		printf "${ltab}%-*s %-*s %-*s ${row}${references}", $szcol1, $name, $szcol2, $type_2, $szcol3, $default;
	}
}

sub process_index
{
	my $line = $_[0];
	my $unique = $_[1];

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
			for ($fields)
			{
				s/,/`,`/g;
			}
			print "CREATE${unique} INDEX `${table_name}_$name` ON `$table_name` (`$fields`);${eol}\n";
		}
		else
		{
			print "CREATE${unique} INDEX ${table_name}_$name ON $table_name ($fields);${eol}\n";
		}
	}
}

sub usage
{
	print "Usage: $0 [c|ibm_db2|mysql|oracle|postgresql|sqlite3]\n";
	print "The script generates Zabbix SQL schemas and C code for different database engines.\n";
	exit;
}

sub process
{
	print $output{"before"};

	$state = "bof";
	$fkeys = "";
	$fkeys_drop = "";
	$sequences = "";
	$uniq = "";
	my ($type, $line);

	open(INFO, $file);	# open the file
	my @lines = <INFO>;	# read it into an array
	close(INFO);		# close the file

	foreach $line (@lines)
	{
		$line =~ tr/\t//d;
		chop($line);

		($type, $line) = split(/\|/, $line, 2);

		if ($type)
		{
			if ($type eq 'FIELD')		{ process_field($line); }
			elsif ($type eq 'INDEX')	{ process_index($line, 0); }
			elsif ($type eq 'TABLE')	{ process_table($line); }
			elsif ($type eq 'UNIQUE')	{ process_index($line, 1); }
		}
	}

	newstate("table");

	print $sequences.$sql_suffix;
	print $fkeys_prefix.$fkeys.$fkeys_suffix;
	print $output{"after"};
}

sub main
{
	if ($#ARGV != 0)
	{
		usage();
	}

	my $format = $ARGV[0];
	$eol = "";
	$fk_bol = "";
	$fk_eol = ";";
	$ltab = "\t";
	$szcol1 = 24;
	$szcol2 = 15;
	$szcol3 = 25;
	$szcol4 = 7;
	$sql_suffix="";
	$fkeys_prefix = "";
	$fkeys_suffix = "";
	my $fkeys_drop_prefix = "";

	if ($format eq 'c')		{ %output = %c; }
	elsif ($format eq 'ibm_db2')	{ %output = %ibm_db2; }
	elsif ($format eq 'mysql')	{ %output = %mysql; }
	elsif ($format eq 'oracle')	{ %output = %oracle; }
	elsif ($format eq 'postgresql')	{ %output = %postgresql; }
	elsif ($format eq 'sqlite3')	{ %output = %sqlite3; }
	else				{ usage(); }

	process();

	if ($format eq "c")
	{
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
		$fkeys_drop_prefix = "const char\t*const db_schema_fkeys_drop[] = {\n";

		print "\n#if defined(HAVE_IBM_DB2)\nconst char\t*const db_schema = \"\\\n";
		%output = %ibm_db2;
		process();
		print $fkeys_drop_prefix.$fkeys_drop.$fkeys_suffix;
		print "#elif defined(HAVE_MYSQL)\nconst char\t*const db_schema = \"\\\n";
		%output = %mysql;
		process();
		print $fkeys_drop_prefix.$fkeys_drop.$fkeys_suffix;
		print "#elif defined(HAVE_ORACLE)\nconst char\t*const db_schema = \"\\\n";
		%output = %oracle;
		process();
		print $fkeys_drop_prefix.$fkeys_drop.$fkeys_suffix;
		print "#elif defined(HAVE_POSTGRESQL)\nconst char\t*const db_schema = \"\\\n";
		%output = %postgresql;
		process();
		print $fkeys_drop_prefix.$fkeys_drop.$fkeys_suffix;
		print "#elif defined(HAVE_SQLITE3)\nconst char\t*const db_schema = \"\\\n";
		%output = %sqlite3;
		process();
		print $fkeys_drop_prefix.$fkeys_drop.$fkeys_suffix;
		print "#endif\t/* HAVE_SQLITE3 */\n";
	}
}

main();
