#!/usr/bin/perl -w
#
# Zabbix
# Copyright (C) 2001-2018 Zabbix SIA
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

my (%output, $insert_into, $fields);

my %ibm_db2 = (
	"database"	=>	"ibm_db2",
	"before"	=>	"",
	"after"		=>	"",
	"exec_cmd"	=>	";\n"
);

my %mysql = (
	"database"	=>	"mysql",
	"before"	=>	"START TRANSACTION;\n",
	"after"		=>	"COMMIT;\n",
	"exec_cmd"	=>	";\n"
);

my %oracle = (
	"database"	=>	"oracle",
	"before"	=>	"SET DEFINE OFF\n",
	"after"		=>	"",
	"exec_cmd"	=>	"\n/\n\n"
);

my %postgresql = (
	"database"	=>	"postgresql",
	"before"	=>	"START TRANSACTION;\n",
	"after"		=>	"COMMIT;\n",
	"exec_cmd"	=>	";\n"
);

my %sqlite3 = (
	"database"	=>	"sqlite3",
	"before"	=>	"BEGIN TRANSACTION;\n",
	"after"		=>	"COMMIT;\n",
	"exec_cmd"	=>	";\n"
);

sub process_table
{
	my $line = $_[0];

	$line = "`$line`" if ($output{'database'} eq 'mysql');

	$insert_into = "INSERT INTO $line";
}

sub process_fields
{
	my $line = $_[0];

	my @array = split(/\|/, $line);

	my $first = 1;
	$fields = "(";

	if ($output{'database'} eq 'mysql')
	{
		foreach (@array)
		{
			$fields = "$fields," if ($first == 0);
			$first = 0;

			$_ =~ s/\s+$//; # remove trailing spaces

			$fields = "$fields`$_`";
		}
	}
	else
	{
		foreach (@array)
		{
			$fields = "$fields," if ($first == 0);
			$first = 0;

			$_ =~ s/\s+$//; # remove trailing spaces

			$fields = "$fields$_";
		}
	}

	$fields = "$fields)";
}

sub process_row
{
	my $line = $_[0];

	my @array = split(/\|/, $line);

	my $first = 1;
	my $values = "(";

	foreach (@array)
	{
		$values = "$values," if ($first == 0);
		$first = 0;

		# remove leading and trailing spaces
		$_ =~ s/^\s+//;
		$_ =~ s/\s+$//;

		if ($_ eq 'NULL')
		{
			$values = "$values$_";
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

			if ($output{'database'} eq 'mysql')
			{
				$_ =~ s/&eol;/\\r\\n/g;
			}
			elsif ($output{'database'} eq 'oracle')
			{
				$_ =~ s/&eol;/' || chr(13) || chr(10) || '/g;
			}
			else
			{
				$_ =~ s/&eol;/\x0D\x0A/g;
			}

			$values = "$values$modifier'$_'";
		}
	}

	$values = "$values)";

	if ($output{'database'} eq 'oracle')
	{
		print "$insert_into $fields\nvalues $values$output{'exec_cmd'}";
	}
	else
	{
		print "$insert_into $fields values $values$output{'exec_cmd'}";
	}
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

	open(INFO, dirname($0)."/../src/data.tmpl");
	my @lines = <INFO>;
	close(INFO);

	open(INFO, dirname($0)."/../src/templates.tmpl");
	push(@lines, <INFO>);
	close(INFO);

	if ($ARGV[0] eq 'ibm_db2')		{ %output = %ibm_db2; }
	elsif ($ARGV[0] eq 'mysql')		{ %output = %mysql; }
	elsif ($ARGV[0] eq 'oracle')		{ %output = %oracle; }
	elsif ($ARGV[0] eq 'postgresql')	{ %output = %postgresql; }
	elsif ($ARGV[0] eq 'sqlite3')		{ %output = %sqlite3; }
	else					{ usage(); }

	print $output{"before"};

	my ($line, $type);
	foreach $line (@lines)
	{
		$line =~ tr/\t//d;
		chop($line);

		($type, $line) = split(/\|/, $line, 2);

		if ($type)
		{
			$type =~ s/\s+$//; # remove trailing spaces

			if ($type eq 'FIELDS')		{ process_fields($line); }
			elsif ($type eq 'TABLE')	{ process_table($line); }
			elsif ($type eq 'ROW')		{ process_row($line); }
		}
	}

	print $output{"after"};
}

main();
