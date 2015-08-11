#!/usr/bin/perl
#
# RDDS rolling week

use lib '/opt/zabbix/scripts';

use strict;
use warnings;
use RSM;
use RSMSLV;

my $cfg_key_in = 'rsm.slv.epp.avail';
my $cfg_key_out = 'rsm.slv.epp.rollweek';

parse_opts();
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my ($from, $till, $value_ts) = get_rollweek_bounds();
my $interval = get_macro_epp_delay($from);
my $cfg_sla = get_macro_epp_rollweek_sla();

my $tlds_ref = get_tlds('EPP');

init_values();

foreach (@$tlds_ref)
{
	$tld = $_;

	my ($itemid_in, $itemid_out, $lastclock) = get_item_data($tld, $cfg_key_in, $cfg_key_out);
	next if (check_lastclock($lastclock, $value_ts, $interval) != SUCCESS);

	my $downtime = get_downtime($itemid_in, $from, $till);
	my $perc = sprintf("%.3f", $downtime * 100 / $cfg_sla);

	push_value($tld, $cfg_key_out, $value_ts, $perc, "result: $perc% (down: $downtime minutes, sla: $cfg_sla, ", ts_str($value_ts), ")");
}

# unset TLD (for the logs)
$tld = undef;

send_values();

slv_exit(SUCCESS);
