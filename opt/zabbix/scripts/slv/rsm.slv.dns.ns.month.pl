#!/usr/bin/perl
#
# DNS NS monthly availability

use lib '/opt/zabbix/scripts';

use strict;
use warnings;
use RSM;
use RSMSLV;

my $cfg_key_in = 'rsm.slv.dns.ns.avail[';
my $cfg_key_out = 'rsm.slv.dns.ns.month[';

parse_opts();
exit_if_running();

set_slv_config(get_rsm_config());

my ($from, $till, $value_ts) = get_month_bounds();

my $interval = $till + 1 - $from;

my $total_values = minutes_last_month();

db_connect();

my $tlds_ref = get_tlds();

init_values();

foreach (@$tlds_ref)
{
	$tld = $_;

	my $lastclock = get_lastclock($tld, $cfg_key_out);
	next if (check_lastclock($lastclock, $value_ts, $interval) != SUCCESS);

	my $items_ref = get_tld_items($tld, $cfg_key_in);

	foreach my $item (@$items_ref)
	{
		my $itemid = $item->[0];
		my $key = $item->[1];

		my $key_out = $cfg_key_out . get_ns_from_key($key). "]";
		my $up_count = get_up_count($itemid);

		my $perc = sprintf("%.3f", $up_count * 100 / $total_values);

		push_value($tld, $key_out, $value_ts, $perc, "$key_out: up:$up_count perc:$perc");
	}
}

# unset TLD (for the logs)
$tld = undef;

send_values();

slv_exit(SUCCESS);

sub get_up_count
{
	my $itemid = shift;

	my $rows_ref = db_select("select count(value) from history_uint where itemid=$itemid and value=" . UP . " and clock between $from and $till");

	return $rows_ref->[0]->[0];
}
