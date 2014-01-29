#!/usr/bin/perl -w
#
# DNS rolling week

use lib '/opt/zabbix/scripts';

use DNSTest;
use DNSTestSLV2;

my $cfg_key_in = 'dnstest.slv.dns.avail';
my $cfg_key_out = 'dnstest.slv.dns.rollweek';

parse_opts();
exit_if_running();

my $config = get_dnstest_config();
set_slv_config($config);

db_connect();

my $interval = get_macro_dns_udp_delay();

my ($from, $till, $value_ts) = get_rollweek_bounds();

my ($itemid_in, $itemid_out, $lastclock) = get_rollweek_data($tld, $cfg_key_in, $cfg_key_out);
exit_if_lastclock($lastclock, $value_ts, $interval);

info("from:$from till:$till value_ts:$value_ts");

my $cfg_sla = get_macro_dns_rollweek_sla();

my $fails = get_down_count($itemid_in, $itemid_out, $from, $till);
my $perc = sprintf("%.3f", $fails * 100 / $cfg_sla);

info("fails:$fails perc:$perc");
send_value($tld, $cfg_key_out, $value_ts, $perc);
exit(SUCCESS);
