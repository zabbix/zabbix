#!/usr/bin/perl -w
#
# DNSSEC proper resolution

use lib '/opt/zabbix/scripts';

use DNSTest;
use DNSTestSLV2;

my $cfg_key_in = 'dnstest.dns.udp.rtt[';
my $cfg_key_out = 'dnstest.slv.dnssec.avail';
my $cfg_dnssec_ec = -204; 	# DNSSEC error

parse_opts();
exit_if_running();

my $config = get_dnstest_config();
set_slv_config($config);

my ($from, $till, $value_ts) = get_minute_bounds();
info("from:$from till:$till value_ts:$value_ts");

db_connect();

my $cfg_minonline = get_macro_dns_probe_online();

my $probes_ref = get_online_probes($from, $till, undef);
my $count = scalar(@$probes_ref);
if ($count < $cfg_minonline)
{
    info("success ($count probes are online, min - $cfg_minonline)");
    send_value($tld, $cfg_key_out, $value_ts, UP);
    exit(SUCCESS);
}

my $hostids_ref = probes2tldhostids($tld, $probes_ref);

my $items = get_items_by_hostids($hostids_ref, $cfg_key_in, 0); # incomplete key

my $values_ref = get_values_by_items($items);

if (scalar(@$values_ref) == 0)
{
    warn("no values found");
    exit(FAIL);
}

info("itemid:", $_->[0], " value:", $_->[1]) foreach (@$values_ref);

$count = get_probes_count($items, $values_ref);
if ($count < $cfg_minonline)
{
    info("success ($count online probes have results, min - $cfg_minonline)");
    send_value($tld, $cfg_key_out, $value_ts, UP);
    exit(SUCCESS);
}

my $success_values = scalar(@$values_ref);
foreach (@$values_ref)
{
    $success_values-- if ($cfg_dnssec_ec == $_->[1]);
}

my $test_result = DOWN;
$test_result = UP if ($success_values * 100 / scalar(@$values_ref) > SLV_UNAVAILABILITY_LIMIT);

info(($test_result == UP ? "success" : "fail"), " (dnssec success: $success_values)");
send_value($tld, $cfg_key_out, $value_ts, $test_result);
exit(SUCCESS);

sub get_values_by_items
{
    my $items_ref = shift;

    my $items_str = "";
    foreach (@$items_ref)
    {
	$items_str .= "," if ("" ne $items_str);
	$items_str .= $_->{'itemid'};
    }

    my $res = db_select("select itemid,value from history where itemid in ($items_str) and clock between $from and $till");

    my @values;
    while (my @row = $res->fetchrow_array)
    {
	push(@values, [$row[0], $row[1]]);
    }

    return \@values;
}

sub get_probes_count
{
    my $items_ref = shift;
    my $values_ref = shift;

    my @hostids;
    foreach my $value_ref (@$values_ref)
    {
	foreach my $item_ref (@$items_ref)
	{
	    if ($value_ref->[0] == $item_ref->{'itemid'})
	    {
		push(@hostids, $item_ref->{'hostid'}) unless ($item_ref->{'hostid'} ~~ @hostids);
		last;
	    }
	}
    }

    return scalar(@hostids);
}
