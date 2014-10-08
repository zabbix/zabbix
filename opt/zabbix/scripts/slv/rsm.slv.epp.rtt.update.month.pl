#!/usr/bin/perl
#
# EPP update-command monthly resolution RTT

use lib '/opt/zabbix/scripts';

use strict;
use warnings;
use RSM;
use RSMSLV;

use constant MIN_UPDATE_ERROR => -208; # minimal UPDATE TIME error
use constant MAX_UPDATE_ERROR => -206; # maximal UPDATE TIME error

my $cfg_key_in = 'rsm.epp.rtt[{$RSM.TLD},update]';
my $cfg_key_out = 'rsm.slv.epp.rtt.update.month';

parse_opts();
exit_if_running();

set_slv_config(get_rsm_config());

my ($from, $till, $value_ts) = get_month_bounds();

my $interval = $till + 1 - $from;

db_connect();

my $cfg_max_value = get_macro_epp_rtt_low('update');
my $cfg_delay = get_macro_epp_delay($from);
my $probe_avail_limit = get_macro_probe_avail_limit();

my $tlds_ref = get_tlds();

init_values();

foreach (@$tlds_ref)
{
    $tld = $_;

    my $lastclock = get_lastclock($tld, $cfg_key_out);
    next if (check_lastclock($lastclock, $value_ts, $interval) != SUCCESS);

    process_slv_monthly($tld, $cfg_key_in, $cfg_key_out, $from, $till, $value_ts, $cfg_delay, $probe_avail_limit,
			\&check_item_value, MIN_UPDATE_ERROR, MAX_UPDATE_ERROR);
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
