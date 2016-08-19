#!/usr/bin/perl
#
# EPP downtime of current month in minutes

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*/.*,$1,; $MYDIR = '..' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;

my $cfg_key_out = 'rsm.probe.online';

parse_opts('now=i');
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my $probe_avail_limit = get_macro_probe_avail_limit();

my $now = (opt('now') ? getopt('now') : time() - PROBE_ONLINE_SHIFT);

my $from = truncate_from($now);
my $till = $from + 59;
my $value_ts = $from;

dbg("selected period: ", selected_period($from, $till), ", with value timestamp: ", ts_full($value_ts));

my $probes_ref = get_probes(ENABLED_DNS);

my $probe_times_ref = get_probe_times($from, $till, $probes_ref);
my @online_probes = keys(%{$probe_times_ref});

init_values();

foreach my $probe (keys(%$probes_ref))
{
	my $itemid = get_probe_online_key_itemid($probe);

	fail(rsm_slv_error()) unless ($itemid);

	next if (!opt('dry-run') && uint_value_exists($value_ts, $itemid) == SUCCESS);

	my @result = grep(/^$probe$/, @online_probes);
	my $status = (@result ? UP : DOWN);

	my $status_str = ($status == UP ? "Up" : "Down");

	push_value("$probe - mon", $cfg_key_out, $value_ts, $status, $status_str);
}

send_values();

slv_exit(SUCCESS);
