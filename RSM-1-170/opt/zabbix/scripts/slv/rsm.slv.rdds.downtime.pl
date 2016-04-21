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

my $delay = get_macro_rdds_delay();
my $now = time();

my ($month_from, undef, $value_ts) = get_month_bounds($now, $delay);
my $month_till = cycle_end($value_ts, $delay);

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

	my $downtime = get_downtime_execute($sth, $itemid, $month_from, $month_till);

	push_value($tld, $cfg_key_out, $value_ts, $downtime, "$downtime minutes of downtime from ",
		ts_full($month_from), " till ", ts_full($month_till));
}

# unset TLD (for the logs)
$tld = undef;

send_values();

slv_exit(SUCCESS);
