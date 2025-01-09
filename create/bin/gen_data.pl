#!/usr/bin/env perl
#
# Copyright (C) 2001-2025 Zabbix SIA
#
# This program is free software: you can redistribute it and/or modify it under the terms of
# the GNU Affero General Public License as published by the Free Software Foundation, version 3.
#
# This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
# without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# See the GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License along with this program.
# If not, see <https://www.gnu.org/licenses/>.

use strict;
use File::Basename;

my (%output, $insert_into, $fields, @values_list);

my %mysql = (
	"database"	=>	"mysql",
	"before"	=>	"START TRANSACTION;\n",
	"after"		=>	"COMMIT;\n",
	"exec_cmd"	=>	";\n"
);

my %postgresql = (
	"database"	=>	"postgresql",
	"before"	=>	"START TRANSACTION;\n",
	"after"		=>	"COMMIT;\n",
	"exec_cmd"	=>	";\n"
);

sub process_table
{
	my $line = $_[0];

	$line = "`$line`" if ($output{'database'} eq 'mysql');

	$insert_into = "INSERT INTO $line";
	@values_list = ();  # Reset the values list when processing a new table
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
	my $split_script_field = 0;

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
			$_ =~ s/&tab;/\t/g;

			if ($output{'database'} eq 'mysql')
			{
				$_ =~ s/&eol;/\\r\\n/g;
				$_ =~ s/&bsn;/\\n/g;
			}
			else
			{
				$_ =~ s/&eol;/\x0D\x0A/g;
				$_ =~ s/&bsn;/\x0A/g;
			}

			$values = "$values$modifier'$_'";
		}
	}

	$values = "$values)";

	# Add the current row's values to the list
	push @values_list, $values;
}

sub flush_bulk_insert
{
	if (@values_list) {
		my $bulk_insert = "$insert_into $fields VALUES ".join(",\n", @values_list).$output{'exec_cmd'};
		print $bulk_insert;
		@values_list = ();  # Clear the values list after printing
	}
}

sub usage
{
	print "Usage: $0 [mysql|postgresql]\n";
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

	open(INFO, dirname($0)."/../src/dashboards.tmpl");
	push(@lines, <INFO>);
	close(INFO);

	if ($ARGV[0] eq 'mysql')		{ %output = %mysql; }
	elsif ($ARGV[0] eq 'postgresql')	{ %output = %postgresql; }
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
			elsif ($type eq 'TABLE')	{ flush_bulk_insert(); process_table($line); }
			elsif ($type eq 'ROW')		{ process_row($line); }
		}
	}

	flush_bulk_insert();  # Ensure the last batch of rows is printed

	print "DELETE FROM changelog$output{'exec_cmd'}";

	print $output{"after"};
}

main();
