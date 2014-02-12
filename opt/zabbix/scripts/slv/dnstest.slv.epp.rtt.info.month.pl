#!/usr/bin/perl -w
#
# EPP info-command monthly resolution RTT

use lib '/opt/zabbix/scripts';

use DNSTest;
use DNSTestSLV;

my $cfg_key_in = 'dnstest.epp.rtt[{$DNSTEST.TLD},info]';
my $cfg_key_out = 'dnstest.slv.epp.rtt.info.month';

parse_opts();
exit_if_running();

set_slv_config(get_dnstest_config());

my ($from, $till, $value_ts) = get_month_bounds();

my $interval = $till + 1 - $from;

db_connect();

my $cfg_max_value = get_macro_epp_rtt('info');
my $cfg_delay = get_macro_epp_delay();

my $tlds_ref = get_tlds();

init_values();

foreach (@$tlds_ref)
{
    $tld = $_;

    my $lastclock = get_lastclock($tld, $cfg_key_out);
    next if (check_lastclock($lastclock, $value_ts, $interval) != SUCCESS);

    process_slv_monthly($tld, $cfg_key_in, $cfg_key_out, $from, $till, $value_ts, $cfg_delay, \&check_item_value, MIN_INFO_ERROR, MAX_INFO_ERROR);
}

# unset TLD (for the logs)
$tld = undef;

send_values();

slv_exit(SUCCESS);

sub check_item_value
{
    my $value = shift;

    return (is_service_error($value) == SUCCESS or $value > RTT_LIMIT_MULTIPLIER * $cfg_max_value) ? FAIL : SUCCESS;
}
