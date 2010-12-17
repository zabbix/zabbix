#!/usr/bin/perl
# 
# ZABBIX
# Copyright (C) 2000-2005 SIA Zabbix
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License version 2 as
# published by the Free Software Foundation
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

use utf8;

use Switch;
use File::Basename;

$file = dirname($0)."/schema.sql";	# Name the file

local $state;
local $output;
local $eol, $fk_bol, $fk_eol;
local $ltab, $szcol1, $szcol2, $szcol3, $szcol4;
local $sequences;
local $sql_suffix;
local $fkeys, $fkeys_prefix, $fkeys_suffix;
local $fkeys_drop;
local $uniq;

%mysql=(
	"database"	=>	"mysql",
	"type"		=>	"sql",
	"before"	=>	"",
	"after"		=>	"",
	"table_options"	=>	" ENGINE=InnoDB",
	"t_bigint"	=>	"bigint unsigned",
	"t_id"		=>	"bigint unsigned",
	"t_integer"	=>	"integer",
	"t_time"	=>	"integer",
	"t_nanosec"	=>	"integer",
# It does not work for MySQL 3.x and <4.x (4.11?)
#	"t_serial"	=>	"serial",
	"t_serial"	=>	"bigint unsigned",
	"t_double"	=>	"double(16,4)",
	"t_varchar"	=>	"varchar",
	"t_char"	=>	"char",
	"t_image"	=>	"longblob",
	"t_history_log"	=>	"text",
	"t_history_text"=>	"text",
	"t_blob"	=>	"blob",
	"t_item_param"	=>	"text",
	"t_cksum_text"	=>	"text"
);

%c=(	"type"		=>	"code",
	"database"	=>	"",
	"after"		=>	"\t{0}\n};\n",
	"t_bigint"	=>	"ZBX_TYPE_UINT",
	"t_id"		=>	"ZBX_TYPE_ID",
	"t_integer"	=>	"ZBX_TYPE_INT",
	"t_time"	=>	"ZBX_TYPE_INT",
	"t_nanosec"	=>	"ZBX_TYPE_INT",
	"t_serial"	=>	"ZBX_TYPE_UINT",
	"t_double"	=>	"ZBX_TYPE_FLOAT",
	"t_varchar"	=>	"ZBX_TYPE_CHAR",
	"t_char"	=>	"ZBX_TYPE_CHAR",
	"t_image"	=>	"ZBX_TYPE_BLOB",
	"t_history_log"	=>	"ZBX_TYPE_TEXT",
	"t_history_text"=>	"ZBX_TYPE_TEXT",
	"t_blob"	=>	"ZBX_TYPE_BLOB",
	"t_item_param"	=>	"ZBX_TYPE_TEXT",
	"t_cksum_text"	=>	"ZBX_TYPE_TEXT"  
);

$c{"before"}="/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include \"common.h\"
#include \"dbschema.h\"

const ZBX_TABLE	tables[]={
";

%oracle=("t_bigint"	=>	"number(20)",
	"database"	=>	"oracle",
	"before"	=>	"",
	"after"		=>	"",
	"type"		=>	"sql",
	"t_id"		=>	"number(20)",
	"t_integer"	=>	"number(10)",
	"t_time"	=>	"number(10)",
	"t_nanosec"	=>	"number(10)",
	"t_serial"	=>	"number(20)",
	"t_double"	=>	"number(20,4)",
	"t_varchar"	=>	"nvarchar2",
	"t_char"	=>	"nvarchar2",
	"t_image"	=>	"blob",
	"t_history_log"	=>	"nclob",
	"t_history_text"=>	"nclob",
	"t_blob"	=>	"nvarchar2(2048)",
	"t_item_param"	=>	"nvarchar2(2048)",
	"t_cksum_text"	=>	"nclob"  
);

%ibm_db2=("t_bigint"	=>	"bigint",
	"database"	=>	"ibm_db2",
	"before"	=>	"",
	"after"		=>	"",
	"type"		=>	"sql",
	"t_bigint"	=>	"bigint",
	"t_id"		=>	"bigint",
	"t_integer"	=>	"integer",
	"t_serial"	=>	"bigint",
	"t_double"	=>	"decfloat(16)",
	"t_varchar"	=>	"varchar",
	"t_char"	=>	"varchar",
	"t_image"	=>	"blob",
	"t_history_log"	=>	"varchar(2048)",
	"t_history_text"=>	"varchar(2048)",
	"t_time"	=>	"integer",
	"t_nanosec"	=>	"integer",
	"t_blob"	=>	"varchar(2048)",
	"t_item_param"	=>	"varchar(2048)",
	"t_cksum_text"	=>	"varchar(2048)"
);

%postgresql=("t_bigint"	=>	"numeric(20)",
	"database"	=>	"postgresql",
	"before"	=>	"",
	"after"		=>	"",
	"type"		=>	"sql",
	"table_options"	=>	" with OIDS",
	"t_id"		=>	"bigint",
	"t_integer"	=>	"integer",
	"t_serial"	=>	"serial",
	"t_double"	=>	"numeric(16,4)",
	"t_varchar"	=>	"varchar",
	"t_char"	=>	"char",
	"t_image"	=>	"bytea",
	"t_history_log"	=>	"text",
	"t_history_text"=>	"text",
	"t_time"	=>	"integer",
	"t_nanosec"	=>	"integer",
	"t_blob"	=>	"text",
	"t_item_param"	=>	"text",
	"t_cksum_text"	=>	"text"  
);

%sqlite3=("t_bigint"	=>	"bigint",
	"database"	=>	"sqlite3",
	"before"	=>	"",
	"after"		=>	"",
	"type"		=>	"sql",
	"t_id"		=>	"bigint",
	"t_integer"	=>	"integer",
	"t_time"	=>	"integer",
	"t_nanosec"	=>	"integer",
	"t_serial"	=>	"integer",
	"t_double"	=>	"double(16,4)",
	"t_varchar"	=>	"varchar",
	"t_char"	=>	"char",
	"t_image"	=>	"longblob",
	"t_history_log"	=>	"text",
	"t_history_text"=>	"text",
	"t_blob"	=>	"blob",
	"t_item_param"	=>	"text",
	"t_cksum_text"	=>	"text"  
);

sub rtrim($)
{
	my $string = shift;
	$string =~ s/(\r|\n)*$//;
	return $string;
}

sub newstate
{
	local $new = $_[0];

	if ($state eq "field")
	{
		if ($output{"type"} eq "sql" && $new eq "index") { print "${pkey}${eol}\n)$output{'table_options'};${eol}\n"; }
		if ($output{"type"} eq "sql" && $new eq "table") { print "${pkey}${eol}\n)$output{'table_options'};${eol}\n"; }
		if ($new eq "field") { print ",${eol}\n" }
	}

	if ($state ne "bof")
	{
		if ($output{"type"} eq "code" && $new eq "table") { print ",\n\t\t{0}\n\t\t}${uniq}\n\t},\n"; $uniq = ""; }
	}

	$state = $new;
}

sub process_table
{
	local $line = $_[0];

	newstate("table");

	($table_name, $pkey, $flags) = split(/\|/, $line, 3);

	if ($output{"type"} eq "code")
	{
		if($flags eq "")
		{
			$flags="0";
		}

		for ($flags)
		{
			s/,/ \| /g;
		}

		print "\t{\"${table_name}\",\t\"${pkey}\",\t${flags},\n\t\t{\n";
	}
	else
	{
		if ($pkey ne "")
		{
			$pkey = ",${eol}\n${ltab}PRIMARY KEY (${pkey})";
		}

		print "CREATE TABLE ${table_name} (${eol}\n";
	}
}

sub process_field
{
	local $line=$_[0];

	newstate("field");

	($name, $type, $default, $null, $flags, $relN, $fk_table, $fk_field, $fk_flags) = split(/\|/, $line, 9);
	($type_short) = split(/\(/, $type, 2);

	if ($output{"type"} eq "code")
	{
		$type = $output{$type_short};

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
			if ($fk_field eq "")
			{
				$fk_field = "${name}";
			}

			$fk_table = "\"${fk_table}\"";
			$fk_field = "\"${fk_field}\"";

			if ($fk_flags eq "")
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

		print "\t\t{\"${name}\",\t$type,\t${flags},\t${fk_table},\t${fk_field},\t${fk_flags}}";
	}
	else
	{
		$a = $output{$type_short};
		$_ = $type;
		s/$type_short/$a/g;
		$type_2 = $_;

		if ($default ne "")
		{
			if ($output{"database"} eq "ibm_db2"){
				$default = "WITH DEFAULT $default";
			}
			else{
				$default = "DEFAULT $default";
			}
		}
		
		if ($output{"database"} eq "mysql")
		{
			@text_fields = ('blob', 'longblob', 'text', 'longtext');

			if (grep /$output{$type_short}/, @text_fields)
			{
				$default="";
			}
		}

		if($output{"database"} eq "ibm_db2"){
			@text_fields = ('blob');
			if(grep /$output{$type_short}/, @text_fields){
				$default="";
			}
		}

		if (($output{"database"} eq "oracle") && (0 == index($type_2, "nvarchar2") || 0 == index($type_2, "nclob")))
		{
			$null = "";
		}
		else
		{
			$null = "${null}";
		}

		$row = "${null}";

		if ($type eq "t_serial")
		{
			if ($output{"database"} eq "sqlite3")
			{
				$row = sprintf("%-*s PRIMARY KEY AUTOINCREMENT", $szcol4, $row);
				$pkey="";
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
			elsif($output{"database"} eq "ibm_db2")
			{
				$row="$row\tGENERATED ALWAYS AS IDENTITY (START WITH 1 INCREMENT BY 1)";
			}
		}

		if ($relN ne "" and $relN ne "-")
		{
			if ($fk_field eq "")
			{
				$fk_field = "${name}";
			}

# RESTRICT may contains new line chars we need to clean them out
			$fk_flags = rtrim ($fk_flags);
			
			if ($fk_flags eq "")
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
			else
			{
				$only = "";
			}

			$cname = "c_${table_name}_${relN}";

			if ($output{"database"} eq "sqlite3")
			{
				$references = " REFERENCES ${fk_table} (${fk_field})${fk_flags}";
			}
			else
			{
				$references = "";
				
				$fkeys = "${fkeys}${fk_bol}ALTER TABLE${only} ${table_name} ADD CONSTRAINT ${cname} FOREIGN KEY (${name}) REFERENCES ${fk_table} (${fk_field})${fk_flags}${fk_eol}\n";

				if ($output{"database"} eq "mysql")
				{
					$fkeys_drop = "${fkeys_drop}${fk_bol}ALTER TABLE${only} ${table_name} DROP FOREIGN KEY ${cname}${fk_eol}\n";
				}
				else
				{
					$fkeys_drop = "${fkeys_drop}${fk_bol}ALTER TABLE${only} ${table_name} DROP CONSTRAINT ${cname}${fk_eol}\n";
				}
			}
		}
		else
		{
			$references = "";
		}

		printf "${ltab}%-*s %-*s %-*s ${row}${references}", $szcol1, $name, $szcol2, $type_2, $szcol3, $default;
	}
}

sub process_index
{
	local $line = $_[0];
	local $unique = $_[1];

	newstate("index");

	($name, $fields) = split(/\|/, $line, 2);

	if ($output{"type"} eq "code")
	{
		if (1 == $unique)
		{
			$uniq = ",\n\t\t\"${fields}\"";
		}
	}
	else
	{
		if (1 == $unique) { $unique = " UNIQUE"; }
		else { $unique = ""; }

		print "CREATE${unique} INDEX ${table_name}_$name\ on $table_name ($fields);${eol}\n";
	}
}

sub usage
{
	printf "Usage: $0 [c|ibm_db2|mysql|oracle|postgresql|sqlite3]\n";
	printf "The script generates Zabbix SQL schemas and C code for different database engines.\n";
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

	open(INFO, $file);			# Open the file
	@lines = <INFO>;			# Read it into an array
	close(INFO);				# Close the file

	foreach $line (@lines)
	{
		$_ = $line;
		$line = tr/\t//d;
		$line=$_;

		chop($line);

		($type, $line) = split(/\|/, $line, 2);

		utf8::decode($type);

		switch ($type)
		{
			case "TABLE"	{ process_table($line); }
			case "INDEX"	{ process_index($line, 0); }
			case "UNIQUE"	{ process_index($line, 1); }
			case "FIELD"	{ process_field($line); }
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

	$format = $ARGV[0];
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
	$fkeys_drop_prefix = "";

	switch ($format)
	{
		case "c"		{ %output = %c; }
		case "ibm_db2"		{ %output = %ibm_db2; }
		case "mysql"		{ %output = %mysql; }
		case "oracle"		{ %output = %oracle; }
		case "postgresql"	{ %output = %postgresql; }
		case "sqlite3"		{ %output = %sqlite3; }
		else			{ usage(); }
	}

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
		$fkeys_prefix = "const char *const db_schema_fkeys[] = {\n";
		$fkeys_suffix = "\tNULL\n};\n";
		$fkeys_drop_prefix = "const char *const db_schema_fkeys_drop[] = {\n";

		print "\n#ifdef HAVE_MYSQL\nconst char *const db_schema= \"\\\n";
		%output = %mysql;
		process();
		print $fkeys_drop_prefix.$fkeys_drop.$fkeys_suffix;
		print "#elif HAVE_ORACLE\nconst char *const db_schema= \"\\\n";
		%output = %oracle;
		process();
		print $fkeys_drop_prefix.$fkeys_drop.$fkeys_suffix;
		print "#elif HAVE_IBM_DB2\nconst char *const db_schema= \"\\\n";
		%output = %ibm_db2;
		process();
		print $fkeys_drop_prefix.$fkeys_drop.$fkeys_suffix;
		print "#elif HAVE_POSTGRESQL\nconst char *const db_schema= \"\\\n";
		%output = %postgresql;
		process();
		print $fkeys_drop_prefix.$fkeys_drop.$fkeys_suffix;
		print "#elif HAVE_SQLITE3\nconst char *const db_schema= \"\\\n";
		%output = %sqlite3;
		process();
		print $fkeys_drop_prefix.$fkeys_drop.$fkeys_suffix;
		print "#endif\t/* HAVE_SQLITE3 */\n";
	}
}

main();
