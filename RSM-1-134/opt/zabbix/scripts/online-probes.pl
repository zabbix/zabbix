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
use Data::Dumper;

parse_opts('from=i', 'till=i');

setopt('nolog');
setopt('dry-run');

if (!opt('from') || !opt('till'))
{
	fail("usage: --from <timestamp> --till <timestamp> [OPTIONS]");
}

set_slv_config(get_rsm_config());

db_connect();

my $from = truncate_from(getopt('from'));
my $till = truncate_till(getopt('till'));

my $probes_ref = get_probes(ENABLED_DNS);
my $result = get_probe_times($from, $till, $probes_ref);

my $printed = 0;
foreach my $probe (keys(%$probes_ref))
{
	if ($result->{$probe} && $result->{$probe}->[0] == $from && $result->{$probe}->[1] == $till)
	{
		print("ONLINE:\n") if ($printed == 0);
		print("  $probe\n");
		$printed = 1;
	}
}

$printed = 0;
foreach my $probe (keys(%$probes_ref))
{
	if (!$result->{$probe})
	{
		print("OFFLINE:\n") if ($printed == 0);
		print("  $probe\n");
		$printed = 1;
	}
}

$printed = 0;
foreach my $probe (keys(%$probes_ref))
{
	if ($result->{$probe} && ($result->{$probe}->[0] != $from || $result->{$probe}->[1] != $till))
	{
		print("ONLINE partly:\n") if ($printed == 0);
		print("  $probe\n");
		while ((my $f = shift(@{$result->{$probe}})) && (my $t = shift(@{$result->{$probe}})))
		{
			print("    ", selected_period($f, $t), "\n");
		}
		$printed = 1;
	}
}
