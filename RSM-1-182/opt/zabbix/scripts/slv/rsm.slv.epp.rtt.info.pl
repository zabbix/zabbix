#!/usr/bin/perl
#
# EPP Query-Command monthly RTT

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*/.*,$1,; $MYDIR = '..' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;
use Parallel;

use constant VALUE_INVALID	=> -1;	# used internally

my $cfg_key_in = 'rsm.epp.rtt[{$RSM.TLD},info]';
my $cfg_keys_out =
{
	'failed'	=> 'rsm.slv.epp.rtt.info.failed',
	'max'		=> 'rsm.slv.epp.rtt.info.max',
	'avg'		=> 'rsm.slv.epp.rtt.info.avg',
	'pfailed'	=> 'rsm.slv.epp.rtt.info.pfailed'
};

parse_opts('tld=s', 'now=i');
exit_if_running();

if (opt('tld') || opt('now'))
{
	setopt('nolog');
	setopt('dry-run');
}

my $now = (opt('now') ? getopt('now') : time());

set_slv_config(get_rsm_config());

db_connect();

my $cfg_max_value = get_macro_epp_rtt_low('info');
my $delay = get_macro_epp_delay($now);

my ($from, $month_till, $value_ts) = get_month_bounds($now, $delay);
my $cycle_till = cycle_end($value_ts, $delay);

my $probes_ref = get_probes();
my $probe_count = scalar(keys(%{$probes_ref}));

my $probe_times_ref = get_probe_times($from, $cycle_till, $probes_ref);

my $tlds_ref;
if (opt('tld'))
{
        fail("TLD ", getopt('tld'), " does not exist.") if (tld_exists(getopt('tld')) == 0);

        $tlds_ref = [ getopt('tld') ];
}
else
{
        $tlds_ref = get_tlds('EPP');
}

my $month_cycles = get_num_cycles($from, $month_till, $delay);

if (opt('debug'))
{
	dbg("$month_cycles month cycles");
	dbg("delay: ", friendly_delay($delay));
	dbg("period: ", selected_period($from, $cycle_till), ", value ts: ", ts_full($value_ts));
}

my $tld_index = 0;
my $tld_count = scalar(@{$tlds_ref});

while ($tld_index < $tld_count)
{
	my $pid = fork_without_pipe();

	if (!defined($pid))
	{
		# max children reached, make sure to handle_children()
	}
	elsif ($pid)
	{
		# parent
		$tld_index++;
	}
	else
	{
		# child
		$tld = $tlds_ref->[$tld_index];

		db_connect();

		my $test_key = $cfg_keys_out->{'failed'};
		my $result;

		if (!opt('dry-run'))
		{
			if (get_lastclock($tld, $test_key, \$result) != SUCCESS)
			{
				wrn("configuration error: EPP monthly info RTT item not found (\"$test_key\")");
				exit(0);
			}

			exit(0) if (uint_value_exists($value_ts, $result->{'itemid'}) == SUCCESS);
		}

		$result = process_slv_monthly($tld, $cfg_key_in, $from, $cycle_till, $value_ts, $delay,
			$probe_times_ref, \&check_item_value);

		init_values();

		if (!$result->{'total_tests'})
		{
			wrn("no values found in the database for a given period");
			exit(0);
		}

		my $failed = $result->{'failed_tests'};
		my $pfailed = (sprintf("%.3f", $result->{'total_tests'} ?
			$result->{'failed_tests'} * 100 / $result->{'total_tests'} : 0));
		my $avg = (sprintf("%.3f", $result->{'successful_tests'} ?
			$result->{'successful_accum'} / $result->{'successful_tests'} : VALUE_INVALID));
		my $max = $month_cycles * $probe_count;

		push_value($tld, $cfg_keys_out->{'failed'},  $value_ts, $failed,  "failed tests (total: ", $result->{'total_tests'}, ")");
		push_value($tld, $cfg_keys_out->{'avg'},     $value_ts, $avg,     "average RTT") unless ($avg == VALUE_INVALID);
		push_value($tld, $cfg_keys_out->{'pfailed'}, $value_ts, $pfailed, "% of failed tests");
		push_value($tld, $cfg_keys_out->{'max'},     $value_ts, $max,     "max tests per month");

		send_values();

		exit(0);
	}

	handle_children();
}

# unset TLD (for the logs)
$tld = undef;

# wait till children finish
while (children_running() > 0)
{
	handle_children();
}

slv_exit(SUCCESS);

sub check_item_value
{
	my $value = shift;

	return (is_service_error($value) == SUCCESS or $value > $cfg_max_value) ? E_FAIL : SUCCESS;
}
