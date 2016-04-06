#!/usr/bin/perl
#
# EPP availability

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*/.*,$1,; $MYDIR = '..' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;

my $cfg_key_in = 'rsm.epp[{$RSM.TLD}';
my $cfg_key_out = 'rsm.slv.epp.avail';

parse_avail_opts();
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my $interval = get_macro_epp_delay();
my $cfg_minonline = get_macro_epp_probe_online();
my $probe_avail_limit = get_macro_probe_avail_limit();

my $clock = (opt('from') ? getopt('from') : $now - $interval - AVAIL_SHIFT_BACK);
my $period = (opt('period') ? getopt('period') : 1);

my $max_avail_time = max_avail_time($now);

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

while ($period > 0)
{
	my ($from, $till, $value_ts) = get_interval_bounds($interval, $clock);

	$period -= $interval / 60;
	$clock += $interval;

	next if ($till > $max_avail_time);

	my $probes_ref = get_online_probes($from, $till, $probe_avail_limit, undef);

	init_values();

	foreach (@$tlds_ref)
	{
		$tld = $_;	# set global variable here

		my $itemid = get_itemid_by_host($tld, $cfg_key_out);
		if (!$itemid)
		{
			wrn("configuration error: ", rsm_slv_error());
			next;
		}

		if (avail_value_exists($value_ts, $itemid) == SUCCESS)
		{
			# value already exists
			next unless (opt('dry-run'));
		}

		process_slv_avail($tld, $cfg_key_in, $cfg_key_out, $from, $till, $value_ts, $cfg_minonline,
			$probe_avail_limit, $probes_ref, \&check_item_values);
	}

	# unset TLD (for the logs)
	$tld = undef;

	send_values();
}

slv_exit(SUCCESS);

# SUCCESS - no values or at least one successful value
# E_FAIL  - all values unsuccessful
sub check_item_values
{
	my $values_ref = shift;

	return SUCCESS if (scalar(@$values_ref) == 0);

	foreach (@$values_ref)
	{
		return SUCCESS if ($_ == UP);
	}

	return E_FAIL;
}
