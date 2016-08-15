#!/usr/bin/perl -w

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
	our $MYDIR2 = $0; $MYDIR2 =~ s,(.*)/.*/.*,$1,; $MYDIR2 = '..' if ($MYDIR2 eq $0);
}
use lib $MYDIR;
use lib $MYDIR2;

use strict;
use warnings;
use RSM;
use RSMSLV;

parse_opts('tld=s', 'from=n', 'till=n');

# do not write any logs
setopt('nolog');

if (opt('debug'))
{
	dbg("command-line parameters:");
	dbg("$_ => ", getopt($_)) foreach (optkeys());
}

set_slv_config(get_rsm_config());

db_connect();

my ($key, $service_type, $delay, $proto, $command);	# $proto is needed for DNS, $command for EPP

my ($from, $till);

$delay = get_macro_epp_delay();

if (opt('from'))
{
	$from = truncate_from(getopt('from'));
}
if (opt('till'))
{
	$till = truncate_till(getopt('till'));
}

info("selected period: ", selected_period($from, $till));

my $tlds_ref;
if (opt('tld'))
{
	fail("TLD ", getopt('tld'), " does not exist.") if (tld_exists(getopt('tld')) == 0);

	$tlds_ref = [ getopt('tld') ];
}
else
{
	$tlds_ref = get_tlds(ENABLED_EPP);
}

foreach (@$tlds_ref)
{
	$tld = $_;

	my $epp_dbl_items_ref = get_epp_dbl_itemids($tld, getopt('probe'), 'rsm.epp.rtt[{$RSM.TLD},');
	my $epp_str_items_ref = get_epp_str_itemids($tld, getopt('probe'), 'rsm.epp.ip[{$RSM.TLD}]');

	my $tests_ref = get_epp_test_values($epp_dbl_items_ref, $epp_str_items_ref, $from, $till, get_valuemaps('epp'), $delay);
}
