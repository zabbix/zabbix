#!/usr/bin/perl -w
#
# DNS NS availability

use lib '/opt/zabbix/scripts';

use RSM;
use RSMSLV;

my $cfg_key_in = 'rsm.dns.udp.rtt[{$RSM.TLD},';
my $cfg_key_out = 'rsm.slv.dns.ns.avail[';

parse_opts();
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my $interval = get_macro_dns_udp_delay();
my $cfg_minonline = get_macro_dns_probe_online();
my $cfg_max_value = get_macro_dns_udp_rtt_high();
my $probe_avail_limit = get_macro_probe_avail_limit();

my ($from, $till, $value_ts) = get_interval_bounds($interval);

my $tlds_ref = get_tlds();

init_values();

foreach (@$tlds_ref)
{
    $tld = $_;

    my $lastclock = get_lastclock($tld, $cfg_key_out);
    next if (check_lastclock($lastclock, $value_ts, $interval) != SUCCESS);

    process_slv_ns_avail($tld, $cfg_key_in, $cfg_key_out, $from, $till, $value_ts, $cfg_minonline,
			 SLV_UNAVAILABILITY_LIMIT, $probe_avail_limit, \&check_item_value);
}

# unset TLD (for the logs)
$tld = undef;

send_values();

slv_exit(SUCCESS);

sub check_item_value
{
    my $value = shift;

    return (is_service_error($value) == SUCCESS or $value > $cfg_max_value) ? FAIL : SUCCESS;
}
