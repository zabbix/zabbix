#!/usr/bin/perl -w
#
# DNSSEC rolling week

use lib '/opt/zabbix/scripts';

use DNSTest;
use DNSTestSLV;

my $cfg_key_in = 'dnstest.slv.dnssec.avail';
my $cfg_key_out = 'dnstest.slv.dnssec.rollweek';

parse_opts();
exit_if_running();

my $config = get_dnstest_config();
set_slv_config($config);

zapi_connect();

my $interval = zapi_get_macro_dns_udp_delay();

my ($from, $till, $value_ts) = get_rollweek_bounds();

exit_if_lastclock($tld, $cfg_key_out, $value_ts, $interval);

info("from:$from till:$till value_ts:$value_ts");

my $cfg_sla = zapi_get_macro_dns_rollweek_sla();

my $result = $zabbix->get('item', {host => $tld, filter => {key_ => $cfg_key_in}});
fail("cannot get itemid (host:$tld key:$cfg_key_in)") unless ($result->{'itemid'});
my $itemid_src = $result->{'itemid'};

$result = $zabbix->get('item', {host => $tld, filter => {key_ => $cfg_key_out}});
fail("cannot get itemid (host:$tld key:$cfg_key_out)") unless ($result->{'itemid'});
my $itemid_dst = $result->{'itemid'};

db_connect();

my $fails = get_down_count($itemid_src, $itemid_dst, $from, $till);
my $perc = sprintf("%.3f", $fails * 100 / $cfg_sla);

info("fails:$fails perc:$perc");
send_value($tld, $cfg_key_out, $value_ts, $perc);
exit(SUCCESS);
