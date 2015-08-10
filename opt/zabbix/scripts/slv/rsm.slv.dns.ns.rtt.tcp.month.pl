#!/usr/bin/perl
#
# DNS monthly resolution RTT (TCP)
#
# 1) run through all periods in a month (rsm.dns.tcp.rtt delay)
# 2) for each period calculate resolution RTT of every NS taking probe status into account
# 3) calculate and save the percentage of successful RTTs

use lib '/opt/zabbix/scripts';

use strict;
use warnings;
use RSM;
use RSMSLV;

my $cfg_key_in = 'rsm.dns.tcp.rtt[{$RSM.TLD},';
my $cfg_key_out = 'rsm.slv.dns.ns.rtt.tcp.month[';

parse_opts();
exit_if_running();

set_slv_config(get_rsm_config());

my ($from, $till, $value_ts) = get_month_bounds();

my $interval = $till + 1 - $from;

db_connect();

my $cfg_max_value = get_macro_dns_tcp_rtt_low();
my $cfg_delay = get_macro_dns_tcp_delay($from);
my $probe_avail_limit = get_macro_probe_avail_limit();

my $tlds_ref = get_tlds();

init_values();

foreach (sort(keys(%$tlds_ref)))
{
	$tld = $_;

	my $lastclock = get_lastclock($tld, $cfg_key_out);
	fail("configuration error: item \"$cfg_key_out\" not found at host \"$tld\"") if ($lastclock == E_FAIL);
	next if (check_lastclock($lastclock, $value_ts, $interval) != SUCCESS);

	process_slv_ns_monthly($tld, $cfg_key_in, $cfg_key_out, $from, $till, $value_ts, $cfg_delay, $probe_avail_limit,
		\&check_item_value);
}

# unset TLD (for the logs)
$tld = undef;

send_values();

slv_exit(SUCCESS);

sub check_item_value
{
	my $value = shift;

	return (is_service_error($value) == SUCCESS or $value > $cfg_max_value) ? E_FAIL : SUCCESS;
}
