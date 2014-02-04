#!/usr/bin/perl -w
#
# DNS monthly resolution RTT (UDP)
#
# 1) run through all periods in a month (dnstest.dns.udp.rtt delay)
# 2) for each period calculate resolution RTT of every NS taking probe status into account
# 3) calculate and save the percentage of successful RTTs

use lib '/opt/zabbix/scripts';

use DNSTest;
use DNSTestSLV;

my $cfg_key_in = 'dnstest.dns.udp.rtt[{$DNSTEST.TLD},';
my $cfg_key_out = 'dnstest.slv.dns.ns.rtt.udp.month[';

parse_opts();
exit_if_running();

set_slv_config(get_dnstest_config());

my ($from, $till, $value_ts) = get_month_bounds();

my $interval = $till + 1 - $from;

zapi_connect();

slv_exit(SUCCESS) if (check_lastclock($tld, $cfg_key_out, $value_ts, $interval) != SUCCESS);

info("from:$from till:$till value_ts:$value_ts");

my $cfg_max_value = zapi_get_macro_dns_udp_rtt();
my $cfg_delay = zapi_get_macro_dns_udp_delay();

db_connect();

process_slv_ns_monthly($tld, $cfg_key_in, $cfg_key_out, $from, $till, $value_ts, $cfg_delay, \&check_item_value);

slv_exit(SUCCESS);

sub check_item_value
{
    my $value = shift;

    return (is_service_error($value) == SUCCESS or $value > RTT_LIMIT_MULTIPLIER * $cfg_max_value) ? FAIL : SUCCESS;
}
