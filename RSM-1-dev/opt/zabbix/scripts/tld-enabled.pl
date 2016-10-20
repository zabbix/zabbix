#!/usr/bin/perl

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use Zabbix;
use RSM;
use RSMSLV;
use Data::Dumper;

sub __get_host_macro($$)
{
	my $hostid = shift;
	my $m = shift;

	my $rows_ref = db_select("select value from hostmacro where hostid=$hostid and macro='$m'");

	return undef unless (1 == scalar(@$rows_ref));

	return $rows_ref->[0]->[0];
}

set_slv_config(get_rsm_config());

parse_opts('tld=s');

db_connect();

my $tlds_ref;
if (opt('tld'))
{
	fail("TLD ", getopt('tld'), " does not exist.") if (tld_exists(getopt('tld')) == 0);

	$tlds_ref = [ getopt('tld') ];
}
else
{
	$tlds_ref = get_tlds(ENABLED_DNS);
}

foreach (@{$tlds_ref})
{
	$tld = $_;

	my $hostid = get_hostid("Template $tld");

	my $dnssec = __get_host_macro($hostid, '{$RSM.TLD.DNSSEC.ENABLED}');
	my $rdds = __get_host_macro($hostid, '{$RSM.TLD.RDDS.ENABLED}');
	my $rdds43 = __get_host_macro($hostid, '{$RSM.TLD.RDDS43.ENABLED}');
	my $rdds80 = __get_host_macro($hostid, '{$RSM.TLD.RDDS80.ENABLED}');
	my $rdap = __get_host_macro($hostid, '{$RSM.TLD.RDAP.ENABLED}');
	my $epp = __get_host_macro($hostid, '{$RSM.TLD.EPP.ENABLED}');

	print("$tld\n");

	print("  DNS\n");

	if (!defined($dnssec))
	{
		print("  WARNING! Macro \"{\$RSM.TLD.DNSSEC.ENABLED}\" not defined!\n");
	}
	elsif ($dnssec)
	{
		print("  DNSSEC\n");
	}

	if (defined($rdds))
	{
		print("  WARNING! Old macro \"{\$RSM.TLD.RDDS.ENABLED}\" exists ({\$RSM.TLD.RDDS.ENABLED}=$rdds)\n");
	}

	print("  RDDS\n") if (defined($rdds43) && defined($rdds80) && defined($rdap) && ($rdds43 || $rdds80 || $rdap));

	if (!defined($rdds43))
	{
		print("  WARNING! Macro \"{\$RSM.TLD.RDDS43.ENABLED}\" not defined!\n");
	}
	elsif ($rdds43)
	{
		print("    RDDS43\n");
	}

	if (!defined($rdds80))
	{
		print("  WARNING! Macro \"{\$RSM.TLD.RDDS80.ENABLED}\" not defined!\n");
	}
	elsif ($rdds80)
	{
		print("    RDDS80\n");
	}

	if (!defined($rdap))
	{
		print("  WARNING! Macro \"{\$RSM.TLD.RDAP.ENABLED}\" not defined!\n");
	}
	elsif ($rdap)
	{
		print("    RDAP\n");
	}

	if (!defined($epp))
	{
		print("  WARNING! Macro \"{\$RSM.TLD.EPP.ENABLED}\" not defined!\n");
	}
	elsif ($epp)
	{
		print("  EPP\n");
	}
}
undef($tld);
