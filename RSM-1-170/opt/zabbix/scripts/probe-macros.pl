#!/usr/bin/perl

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
}

use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;

parse_opts();

setopt('nolog');
setopt('dry-run');

my @probes;

set_slv_config(get_rsm_config());

db_connect();

my $result = get_probe_macros();

foreach my $probe (keys(%{$result}))
{
	print($probe, "\n-------------------------\n");

	foreach my $macro (keys(%{$result->{$probe}}))
	{
		print("  $macro\t: ", $result->{$probe}->{$macro}, "\n");
	}
}
