#!/usr/bin/perl
#
# RDDS downtime of current month in minutes

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*/.*,$1,; $MYDIR = '..' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;

my $cfg_key_in = 'rsm.slv.rdds.avail';
my $cfg_key_out = 'rsm.slv.rdds.downtime';

parse_opts();
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my $interval = get_macro_rdds_delay();

# second one is unused
my ($from, $till, $value_ts) = get_interval_bounds($interval);

my ($curmon_from) = get_curmon_bounds();
my $curmon_till = $from;

my %tld_items;

my $tlds_ref = get_tlds();

# just collect itemids
foreach (@$tlds_ref)
{
	$tld = $_; # set global variable here

	my $itemid = get_itemid_by_host($tld, $cfg_key_in);

	next unless ($itemid);

	# for future calculation of downtime
	$tld_items{$tld} = $itemid;
}

init_values();

# use bind for faster execution of the same SQL query
my $sth = get_downtime_prepare();

foreach (keys(%tld_items))
{
	$tld = $_; # set global variable here

	my $itemid = $tld_items{$tld};

	my $downtime = get_downtime_execute($sth, $itemid, $curmon_from, $curmon_till);

	push_value($tld, $cfg_key_out, $value_ts, $downtime, "$downtime minutes of downtime from ",
		ts_str($curmon_from), " ($curmon_from) till ", ts_str($curmon_till), " ($curmon_till)");
}

# unset TLD (for the logs)
$tld = undef;

send_values();

slv_exit(SUCCESS);
