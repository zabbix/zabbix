#!/usr/bin/perl -w
#
# DNS NS monthly availability

use lib '/opt/zabbix/scripts';

use DNSTest;
use DNSTestSLV;

my $cfg_key_in = 'dnstest.slv.dns.ns.avail[';
my $cfg_key_out = 'dnstest.slv.dns.ns.month[';

parse_opts();
exit_if_running();

set_slv_config(get_dnstest_config());

my ($from, $till, $value_ts) = get_month_bounds();

my $interval = $till + 1 - $from;

my $total_values = minutes_last_month();

db_connect();

my $tlds_ref = get_tlds();

init_values();

foreach (@$tlds_ref)
{
    $tld = $_;

    next if (check_lastclock($tld, $cfg_key_out, $value_ts, $interval) != SUCCESS);

    my $items_ref = get_tld_items($tld, $cfg_key_in);

    foreach my $item (@$items_ref)
    {
	my $itemid = $item->[0];
	my $key = $item->[1];

	my $key_out = $cfg_key_out . get_ns_from_key($key). "]";
	my $up_count = get_up_count($itemid);

	my $perc = sprintf("%.3f", $up_count * 100 / $total_values);

	info("$key_out: up:$up_count perc:$perc");
	push_value($tld, $key_out, $value_ts, $perc);
    }
}

send_values();

slv_exit(SUCCESS);

sub get_up_count
{
    my $itemid = shift;

    my $res = db_select("select count(value) from history_uint where itemid=$itemid and value=" . UP . " and clock between $from and $till");

    return ($res->fetchrow_array)[0];
}
