#!/usr/bin/perl -w
#
# DNS rolling week

use lib '/opt/zabbix/scripts';

use RSM;
use RSMSLV;

my $cfg_key_in = 'rsm.slv.dns.avail';
my $cfg_key_out = 'rsm.slv.dns.rollweek';

parse_opts();
exit_if_running();

set_slv_config(get_rsm_config());

my ($from, $till, $value_ts) = get_rollweek_bounds();

db_connect();

my $interval = get_macro_dns_udp_delay($from);
my $cfg_sla = get_macro_dns_rollweek_sla();

my $tlds_ref = get_tlds();

init_values();

foreach (@$tlds_ref)
{
    $tld = $_;

    my ($itemid_in, $itemid_out, $lastclock) = get_item_data($tld, $cfg_key_in, $cfg_key_out);
    next if (check_lastclock($lastclock, $value_ts, $interval) != SUCCESS);

    my $fails = get_down_count($itemid_in, $from, $till);
    $fails *= $interval / 60 if ($interval > 60);

    my $perc = sprintf("%.3f", $fails * 100 / $cfg_sla);

    info("result: $perc % (down: $fails minutes, interval: $interval)");
    push_value($tld, $cfg_key_out, $value_ts, $perc);
}

# unset TLD (for the logs)
$tld = undef;

send_values();

slv_exit(SUCCESS);
