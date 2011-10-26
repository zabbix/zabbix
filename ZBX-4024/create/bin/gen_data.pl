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

$file = dirname($0)."/../src/data.tmpl";	# Name the file
open(INFO, $file);			# Open the file
@lines = <INFO>;			# Read it into an array
close(INFO);				# Close the file

local $output;

%mysql=(
	"database"	=>	"mysql",
	"before"	=>	"START TRANSACTION;\n",
	"after"		=>	"COMMIT;\n",
	"exec_cmd"	=>	";\n"
);

%oracle=(
	"database"	=>	"oracle",
	"before"	=>	"",
	"after"		=>	"",
	"exec_cmd"	=>	"\n/\n\n"
);

%ibm_db2=(
	"database"	=>	"ibm_db2",
	"before"	=>	"",
	"after"		=>	"",
	"exec_cmd"	=>	";\n"
);

%postgresql=(
	"database"	=>	"postgresql",
	"before"	=>	"START TRANSACTION;\n",
	"after"		=>	"COMMIT;\n",
	"exec_cmd"	=>	";\n"
);

%sqlite=(
	"database"	=>	"sqlite",
	"before"	=>	"START TRANSACTION;\n",
	"after"		=>	"COMMIT;\n",
	"exec_cmd"	=>	";\n"
);

sub process_table
{
	local $line = $_[0];

	$insert_into = "INSERT INTO $line";
}

sub process_fields
{
	local $line = $_[0];

	@array = split(/\|/, $line);

	$first = 1;
	$fields = "(";
	foreach (@array) {
		if ($first == 0) {
			$fields = "$fields,";
		}
		$first = 0;

		$_ =~ s/\s+$//; #remove trailing spaces

		$fields = "$fields$_";
	}
	$fields = "$fields)";
}

sub process_row
{
	local $line = $_[0];

	@array = split(/\|/, $line);

	$first = 1;
	$values = "(";
	foreach (@array) {
		if ($first == 0) {
			$values = "$values,";
		}
		$first = 0;

		# remove leading and trailing spaces
		$_ =~ s/^\s+//;
		$_ =~ s/\s+$//;

		if ($_ eq 'NULL') {
			$values = "$values$_";
		}
		else {
			$values = "$values'$_'";
		}
	}
	$values = "$values)";
	print "$insert_into $fields values $values$output{'exec_cmd'}";
}

sub usage
{
	printf "Usage: $0 [ibm_db2|mysql|oracle|postgresql|sqlite]\n";
	printf "The script generates ZABBIX SQL schemas and C/PHP code for different database engines.\n";
	exit;
}

sub main
{
	if ($#ARGV != 0)
	{
		usage();
	}

	switch ($ARGV[0]) {
		case "ibm_db2"		{ %output = %ibm_db2; }
		case "mysql"		{ %output = %mysql; }
		case "oracle"		{ %output = %oracle; }
		case "postgresql"	{ %output = %postgresql; }
		case "sqlite"		{ %output = %sqlite; }
		else			{ usage(); }
	}

	print $output{"before"};

	foreach $line (@lines)
	{
		$_ = $line;
		$line = tr/\t//d;
		$line=$_;
	
		chop($line);
	
		($type, $line) = split(/\|/, $line, 2);

		utf8::decode($type);

		$type =~ s/\s+$//; #remove trailing spaces
	
		switch ($type) {
			case "TABLE"	{ process_table($line); }
			case "FIELDS"	{ process_fields($line); }
			case "ROW"	{ process_row($line); }
		}
	}
}

main();
print $output{"after"};
