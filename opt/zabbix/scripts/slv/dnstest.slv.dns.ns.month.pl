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

my $config = get_dnstest_config();
set_slv_config($config);

my ($from, $till, $value_ts) = get_month_bounds();

zapi_connect();

my $interval = $till + 1 - $from;

exit_if_lastclock($tld, $cfg_key_out, $value_ts, $interval);

info("from:$from till:$till value_ts:$value_ts");

# First we need to get the list of items where the values should be saved.
my $result = $zabbix->get('item', {output => ['key_'], host => $tld, startSearch => 1, search => {key_ => $cfg_key_out}});

my @out_keys;
if ('ARRAY' eq ref($result))
{
    push(@out_keys, $_->{'key_'}) foreach (@$result);
}
elsif (defined($result->{'itemid'}))
{
    push(@out_keys, $result);
}
else
{
    fail("no output items at host $tld ($cfg_key_out.*)");
}

$result = $zabbix->get('item', {output => ['key_'], host => $tld, startSearch => 1, search => {key_ => $cfg_key_in}});

my @items;
if ('ARRAY' eq ref($result))
{
    push(@items, $_) foreach (@$result);
}
elsif (defined($result->{'itemid'}))
{
    push(@items, $result);
}
else
{
    fail("no input items at host $tld ($cfg_key_in.*))");
}

db_connect();

my $total_values = minutes_last_month();
foreach my $item (@items)
{
    my $itemid = $item->{'itemid'};
    my $key = $item->{'key_'};
    my $key_out = $cfg_key_out . get_ns_from_key($key). "]";
    my $up_count = get_up_count($itemid);

    my $perc = sprintf("%.3f", $up_count * 100 / $total_values);

    info("$key_out: up:$up_count perc:$perc");
    send_value($tld, $key_out, $value_ts, $perc);
}

slv_exit(SUCCESS);

sub get_up_count
{
    my $itemid = shift;

    my $res = db_select("select count(value) from history_uint where itemid=$itemid and value=" . UP . " and clock between $from and $till");

    return ($res->fetchrow_array)[0];
}
