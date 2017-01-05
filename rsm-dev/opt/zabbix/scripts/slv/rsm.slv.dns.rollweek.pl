#!/usr/bin/perl
#
# DNS rolling week

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*/.*,$1,; $MYDIR = '..' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;

my $cfg_key_in = 'rsm.slv.dns.avail';
my $cfg_key_out = 'rsm.slv.dns.rollweek';

parse_rollweek_opts();
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my ($from, $till, $value_ts) = get_rollweek_bounds(getopt('from'));
my $interval = get_macro_dns_udp_delay($from);
my $cfg_sla = get_macro_dns_rollweek_sla();

dbg("selecting period ", selected_period($from, $till), " (value_ts:", ts_str($value_ts), ")");

my $tlds_ref;
if (opt('tld'))
{
        fail("TLD ", getopt('tld'), " does not exist.") if (tld_exists(getopt('tld')) == 0);

        $tlds_ref = [ getopt('tld') ];
}
else
{
        $tlds_ref = get_tlds();
}

init_values();

foreach (@$tlds_ref)
{
	# NB! This is needed in order to set the value globally.
	$tld = $_;

	my ($itemid_in, $itemid_out, $lastclock) = get_item_data($tld, $cfg_key_in, $cfg_key_out);

        if (rollweek_value_exists($value_ts, $itemid_out) == SUCCESS)
        {
                # value already exists
                next unless (opt('dry-run'));
        }

	my $downtime = get_downtime($itemid_in, $from, $till);
	my $perc = sprintf("%.3f", $downtime * 100 / $cfg_sla);

	push_value($tld, $cfg_key_out, $value_ts, $perc, "result: $perc% (down: $downtime minutes, sla: $cfg_sla, ", ts_str($value_ts), ")");
}

# unset TLD (for the logs)
$tld = undef;

send_values();

slv_exit(SUCCESS);
