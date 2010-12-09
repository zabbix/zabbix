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
open(INFO, $file);			# Open the file
@lines = <INFO>;			# Read it into an array
close(INFO);				# Close the file

local $output;

%mysql=(
	"database"	=>	"mysql",
	"type"		=>	"sql",
	"before"	=>	"",
	"after"		=>	"",
	"table_options"	=>	" ENGINE=InnoDB",
	"exec_cmd"	=>	";\n",
	"t_bigint"	=>	"bigint unsigned",
	"t_id"		=>	"bigint unsigned",
	"t_integer"	=>	"integer",
	"t_time"	=>	"integer",
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
	"exec_cmd"	=>	"\n",
	"t_bigint"	=>	"ZBX_TYPE_UINT",
	"t_id"		=>	"ZBX_TYPE_ID",
	"t_integer"	=>	"ZBX_TYPE_INT",
	"t_time"	=>	"ZBX_TYPE_INT",
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

ZBX_TABLE	tables[]={
";

%oracle=("t_bigint"	=>	"number(20)",
	"database"	=>	"oracle",
	"before"	=>	"",
	"after"		=>	"",
	"type"		=>	"sql",
	"exec_cmd"	=>	"\n/\n\n",
	"t_id"		=>	"number(20)",
	"t_integer"	=>	"number(10)",
	"t_time"	=>	"number(10)",
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

%ibm_db2=(
	"database"	=>	"ibm_db2",
	"type"		=>	"sql",
	"before"	=>	"",
	"after"		=>	"",
	"exec_cmd"	=>	";\n",
	"t_bigint"	=>	"bigint",
	"t_id"		=>	"bigint",
	"t_integer"	=>	"integer",
	"t_time"	=>	"integer",
	"t_serial"	=>	"bigint",
	"t_double"	=>	"decfloat(16)",
	"t_varchar"	=>	"varchar",
	"t_char"	=>	"varchar",
	"t_image"	=>	"blob",
	"t_history_log"	=>	"varchar(2048)",
	"t_history_text"=>	"varchar(2048)",
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
	"exec_cmd"	=>	";\n",
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
	"t_blob"	=>	"text",
	"t_item_param"	=>	"text",
	"t_cksum_text"	=>	"text"  
);

%sqlite=("t_bigint"	=>	"bigint",
	"database"	=>	"sqlite",
	"before"	=>	"BEGIN TRANSACTION;\n",
	"after"		=>	"COMMIT;\n",
	"type"		=>	"sql",
	"exec_cmd"	=>	";\n",
	"t_id"		=>	"bigint",
	"t_integer"	=>	"integer",
	"t_time"	=>	"integer",
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

sub newstate
{
	local $new=$_[0];

	switch ($state)
	{
		case "field"	{
			if($output{"type"} eq "sql" && $new eq "index") { print "$pkey\n)$output{'table_options'}$output{'exec_cmd'}"; }
			if($output{"type"} eq "sql" && $new eq "table") { print "$pkey\n)$output{'table_options'}$output{'exec_cmd'}"; }
			if($output{"type"} eq "code" && $new eq "table") { print ",\n\t\t{0}\n\t\t}\n\t},$output{'exec_cmd'}"; }
			if($new eq "field") { print ",\n" }
		}
		case "index"	{
			if($output{"type"} eq "sql" && $new eq "table") { print "${statements}"; }
			if($output{"type"} eq "code" && $new eq "table") { print ",\n\t\t{0}\n\t\t}\n\t},$output{'exec_cmd'}"; }
		}
	 	case "table"	{
			if($output{"type"} eq "sql" && $new eq "table") { print "${statements}"; }
			print "";
		}
	}
	$state=$new;
}

sub process_table
{
	local $line=$_[0];

	newstate("table");
	($table_name,$pkey,$flags)=split(/\|/, $line,4);
	$statements="";

	if($output{"type"} eq "code")
	{
#	        {"services",    "serviceid",    ZBX_SYNC,
		if($flags eq "")
		{
			$flags="0";
		}
		for ($flags) {
			s/,/ \| /;
		}
		print "\t{\"${table_name}\",\t\"${pkey}\",\t${flags},\n\t\t{\n";
	}
	else
	{
		if($pkey ne "")
		{
			$pkey=",\n\tPRIMARY KEY ($pkey)";
		}
		else
		{
			$pkey="";
		}

		print "CREATE TABLE $table_name (\n";
	}
}

sub process_field
{
	local $line=$_[0];

	newstate("field");
	($name,$type,$default,$null,$flags,$rel)=split(/\|/, $line,6);
	($type_short)=split(/\(/, $type,2);
	if($output{"type"} eq "code")
	{
		$type=$output{$type_short};
#{"linkid",      ZBX_TYPE_INT,   ZBX_SYNC},
		if ($null eq "NOT NULL") {
			if ($flags ne "0") {
				$flags="ZBX_NOTNULL | ".$flags;
			} else {
				$flags="ZBX_NOTNULL";
			}
		}
		for ($flags) {
			s/,/ \| /;
		}
		if ($rel) {
			$rel = "\"${rel}\"";
		} else {
			$rel = "NULL";
		}
		print "\t\t{\"${name}\",\t$type,\t${flags},\t${rel}}";
	}
	else
	{
		$a=$output{$type_short};
		$_=$type;
		s/$type_short/$a/g;
		$type_2=$_;

		if($default ne ""){
			$default="DEFAULT $default"; 
		}
		
		if($output{"database"} eq "mysql"){
			@text_fields = ('blob','longblob','text','longtext');
			if(grep /$output{$type_short}/, @text_fields){ 
				$default=""; 
			}
		}

		if($output{"database"} eq "ibm_db2"){
			@text_fields = ('blob');
			if(grep /$output{$type_short}/, @text_fields){ 
				$default=""; 
			}
		}

		# Special processing for Oracle "default 'ZZZ' not null" -> "default 'ZZZ'. NULL=='' in Oracle!"
		if(($output{"database"} eq "oracle") && ((0==index($type_2,"nvarchar2")) || (0==index($type_2,"nclob"))))
		{
			$null="";
		}
		else
		{
			$null="\t${null}";
		}

		$row="\t$name\t\t$type_2\t\t$default${null}";

		if($type eq "t_serial")
		{
			if($output{"database"} eq "sqlite")
			{
				$row="$row\tPRIMARY KEY AUTOINCREMENT";
				$pkey="";
			}
			elsif($output{"database"} eq "mysql")
			{
				$row="$row\tauto_increment unique";
			}
			elsif($output{"database"} eq "oracle")
			{
				$statements="${statements}CREATE SEQUENCE ${table_name}_seq\n";
				$statements="${statements}START WITH 1\n";
				$statements="${statements}INCREMENT BY 1\n";
				$statements="${statements}NOMAXVALUE$output{'exec_cmd'}";
				$statements="${statements}CREATE TRIGGER ${table_name}_tr\n";
				$statements="${statements}BEFORE INSERT ON ${table_name}\n";
				$statements="${statements}FOR EACH ROW\n";
				$statements="${statements}BEGIN\n";
				$statements="${statements}SELECT proxy_history_seq.nextval INTO :new.id FROM dual;\n";
				$statements="${statements}END;$output{'exec_cmd'}";
			}
			elsif($output{"database"} eq "ibm_db2")
			{
				$row="$row\tGENERATED ALWAYS AS IDENTITY (START WITH 1 INCREMENT BY 1)";
			}
		}
		print $row;
	}
}

sub process_index
{
	local $line=$_[0];
	local $unique=$_[1];

	newstate("index");

	if($output{"type"} eq "code")
	{
		return;
	}

	($name,$fields)=split(/\|/, $line,2);

	if($unique == 1)
	{
		print "CREATE UNIQUE INDEX ${table_name}_$name\ on $table_name ($fields)$output{'exec_cmd'}";
	}
	else
	{
		print "CREATE INDEX ${table_name}_$name\ on $table_name ($fields)$output{'exec_cmd'}";
	}
}

sub usage
{
	printf "Usage: $0 [c|ibm_db2|mysql|oracle|php|postgresql|sqlite]\n";
	printf "The script generates ZABBIX SQL schemas and C/PHP code for different database engines.\n";
	exit;
}

sub main
{
	if($#ARGV!=0)
	{
		usage();
	};

	$format=$ARGV[0];
	switch ($format) {
		case "c"		{ %output=%c; }
		case "ibm_db2"		{ %output=%ibm_db2; }
		case "mysql"		{ %output=%mysql; }
		case "oracle"		{ %output=%oracle; }
		case "php"		{ %output=%php; }
		case "postgresql"	{ %output=%postgresql; }
		case "sqlite"		{ %output=%sqlite; }
		else			{ usage(); }
	}

	print $output{"before"};

	foreach $line (@lines)
	{
		$_ = $line;
		$line = tr/\t//d;
		$line=$_;
	
		chop($line);
	
		($type,$line)=split(/\|/, $line,2);

		utf8::decode($type);
	
		switch ($type) {
			case "TABLE"	{ process_table($line); }
			case "INDEX"	{ process_index($line,0); }
			case "UNIQUE"	{ process_index($line,1); }
			case "FIELD"	{ process_field($line); }
		}
	}
}

main();
newstate("table");
print $output{"after"};
