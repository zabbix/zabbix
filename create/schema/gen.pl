#!/usr/bin/perl
# 
# ZABBIX
# Copyright (C) 2000-2005 SIA Zabbix
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

use Switch;

$file = 'schema_new.sql';	# Name the file
open(INFO, $file);		# Open the file
@lines = <INFO>;		# Read it into an array
close(INFO);			# Close the file

%mysql=("t_bigint"	=>	"bigint unsigned",
	"t_id"		=>	"bigint unsigned",
	"t_integer"	=>	"integer",
	"t_time"	=>	"integer",
	"t_serial"	=>	"serial",
	"t_double"	=>	"double",
	"t_varchar"	=>	"varchar",
	"t_char"	=>	"char",
	"t_image"	=>	"longblob",
	"t_history_log"	=>	"text",
	"t_history_text"=>	"text",
	"t_blob"	=>	"blob"
);

%oracle=("t_bigint"	=>	"bigint",
	"t_id"		=>	"bigint",
	"t_integer"	=>	"integer",
	"t_serial"	=>	"serial",
	"t_double"	=>	"double",
	"t_varchar"	=>	"varchar",
	"t_char"	=>	"char",
	"t_image"	=>	"longblob",
	"t_history_log"	=>	"text",
	"t_history_text"=>	"text",
	"t_blob"	=>	"blob"
);

%postgresql=("t_bigint"	=>	"bigint",
	"t_id"		=>	"bigint",
	"t_integer"	=>	"integer",
	"t_serial"	=>	"serial",
	"t_double"	=>	"double",
	"t_varchar"	=>	"varchar",
	"t_char"	=>	"char",
	"t_image"	=>	"longblob",
	"t_history_log"	=>	"text",
	"t_history_text"=>	"text",
	"t_blob"	=>	"blob"
);

%sqlite=("t_bigint"	=>	"bigint",
	"t_id"		=>	"bigint",
	"t_integer"	=>	"integer",
	"t_serial"	=>	"serial",
	"t_double"	=>	"double",
	"t_varchar"	=>	"varchar",
	"t_char"	=>	"char",
	"t_image"	=>	"longblob",
	"t_history_log"	=>	"text",
	"t_history_text"=>	"text",
	"t_blob"	=>	"blob"
);

sub newstate
{
	local $new=$_[0];

	switch ($state)
	{
		case "field"	{
			if($new eq "index") { print $pkey; }
			if($new eq "table") { print $pkey; }
			if($new eq "field") { print ",\n" }
		}
		case "index"	{
			if($new eq "table") { print "\n" }
		}
		case "table"	{ print ""; }
	}
	$state=$new;
}

sub process_table
{
	local $line=$_[0];

	newstate("table");
	($table_name,$pkey,$flags)=split(/\|/, $line,4);
	if($pkey ne "")	{ $pkey=",\n\tPRIMARY KEY ($pkey)\n);\n" }
	else { $pkey="\n);\n"; }
	print "CREATE TABLE $table_name (\n";
}

sub process_field
{
	local $line=$_[0];

	newstate("field");
	($name,$type,$default,$null,$flags)=split(/\|/, $line,5);
	($type_short)=split(/\(/, $type,2);
	$a=$mysql{$type_short};
	$_=$type;
	s/$type_short/$a/g;
	$type_2=$_;
	if($default ne "")	{ $default="DEFAULT $default"; }
	print "\t$name\t\t$type_2\t\t$default\t$null";
}

sub process_index
{
	local $line=$_[0];
	local $unique=$_[1];

	newstate("index");
	($name,$fields)=split(/\|/, $line,2);
	if($unique == 1)
	{
		print "CREATE UNIQUE INDEX ${table_name}_$name\ on $table_name ($fields);\n";
	}
	else
	{
		print "CREATE INDEX ${table_name}_$name\ on $table_name ($fields);\n";
	}
}

foreach $line (@lines)
{
	$_ = $line;
	$line = tr/\t//d;
	$line=$_;

	chop($line);

	($type,$line)=split(/\|/, $line,2);

	switch ($type) {
		case "TABLE"	{ process_table($line); }
		case "INDEX"	{ process_index($line,0); }
		case "UNIQUE"	{ process_index($line,1); }
		case "FIELD"	{ process_field($line); }
	}
}

newstate("table");
