#!/usr/bin/perl
#
# Zabbix
# Copyright (C) 2000-2011 Zabbix SIA
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
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

use Switch;
use File::Basename;

$file = dirname($0)."/../src/data.tmpl";	# name the file
open(INFO, $file);				# open the file
@lines = <INFO>;				# read it into an array
close(INFO);					# close the file

my $output;

%ibm_db2 = (
	"database"	=>	"ibm_db2",
	"before"	=>	"",
	"after"		=>	"",
	"exec_cmd"	=>	";\n"
);

%mysql = (
	"database"	=>	"mysql",
	"before"	=>	"START TRANSACTION;\n",
	"after"		=>	"COMMIT;\n",
	"exec_cmd"	=>	";\n"
);

%oracle = (
	"database"	=>	"oracle",
	"before"	=>	"SET DEFINE OFF\n",
	"after"		=>	"",
	"exec_cmd"	=>	"\n/\n\n"
);

%postgresql = (
	"database"	=>	"postgresql",
	"before"	=>	"START TRANSACTION;\n",
	"after"		=>	"COMMIT;\n",
	"exec_cmd"	=>	";\n"
);

%sqlite3 = (
	"database"	=>	"sqlite3",
	"before"	=>	"BEGIN TRANSACTION;\n",
	"after"		=>	"COMMIT;\n",
	"exec_cmd"	=>	";\n"
);

sub process_table
{
	my $line = $_[0];

	$insert_into = "INSERT INTO $line";
}

sub process_fields
{
	my $line = $_[0];

	@array = split(/\|/, $line);

	$first = 1;
	$fields = "(";

	foreach (@array)
	{
		if ($first == 0)
		{
			$fields = "$fields,";
		}
		$first = 0;

		$_ =~ s/\s+$//; # remove trailing spaces

		$fields = "$fields$_";
	}

	$fields = "$fields)";
}

sub process_row
{
	my $line = $_[0];

	@array = split(/\|/, $line);

	foreach (@array)
	{
		$_ =~ s/&pipe;/|/;
	}

	$first = 1;
	$values = "(";

	foreach (@array)
	{
		if ($first == 0)
		{
			$values = "$values,";
		}
		$first = 0;

		# remove leading and trailing spaces
		$_ =~ s/^\s+//;
		$_ =~ s/\s+$//;

		# escape single quotes
		switch ($output{'database'})
		{
			case 'postgresql'
			{
				$_ =~ s/\\/\\\\/g;
				$_ =~ s/'/''/g;
			}
			case 'mysql'
			{
				$_ =~ s/\\/\\\\/g;
				$_ =~ s/'/\\'/g;
			}
			else
			{
				$_ =~ s/'/''/g;
			}
		}

		if ($_ eq 'NULL')
		{
			$values = "$values$_";
		}
		else
		{
			$values = "$values'$_'";
		}
	}

	$values = "$values)";

	print "$insert_into $fields values $values$output{'exec_cmd'}";
}

sub usage
{
	print "Usage: $0 [ibm_db2|mysql|oracle|postgresql|sqlite3]\n";
	print "The script generates Zabbix SQL data files for different database engines.\n";
	exit;
}

sub main
{
	if ($#ARGV != 0)
	{
		usage();
	}

	switch ($ARGV[0])
	{
		case "ibm_db2"		{ %output = %ibm_db2; }
		case "mysql"		{ %output = %mysql; }
		case "oracle"		{ %output = %oracle; }
		case "postgresql"	{ %output = %postgresql; }
		case "sqlite3"		{ %output = %sqlite3; }
		else			{ usage(); }
	}

	print $output{"before"};

	foreach $line (@lines)
	{
		$line =~ tr/\t//d;
		chop($line);

		($type, $line) = split(/\|/, $line, 2);

		$type =~ s/\s+$//; # remove trailing spaces

		switch ($type)
		{
			case "TABLE"	{ process_table($line); }
			case "FIELDS"	{ process_fields($line); }
			case "ROW"	{ process_row($line); }
		}
	}

	print $output{"after"};
}

main();
