#!/usr/bin/perl
#
# RDDS Update Time

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

my $keys =
{
	'totals' =>
	{
		'failed'	=> 'rsm.slv.rdds.upd.failed',
		'max'		=> 'rsm.slv.rdds.upd.max',
		'avg'		=> 'rsm.slv.rdds.upd.avg',
		'pfailed'	=> 'rsm.slv.rdds.upd.pfailed'
	},
	'services' =>
	[
		{
			'service' => 'RDDS43',
			'keys' =>
			{
				'in' => 'rsm.rdds.43.upd[{$RSM.TLD}]',
				'out' =>
				{
					'failed'	=> 'rsm.slv.rdds43.upd.failed',
					'max'		=> 'rsm.slv.rdds43.upd.max',
					'avg'		=> 'rsm.slv.rdds43.upd.avg',
					'pfailed'	=> 'rsm.slv.rdds43.upd.pfailed'
				}
			}
		},
		{
			'service' => 'RDAP',
			'keys' =>
			{
				'in' => 'rsm.rdds.rdap.upd[{$RSM.TLD}]',
				'out' =>
				{
					'failed'	=> 'rsm.slv.rdap.upd.failed',
					'max'		=> 'rsm.slv.rdap.upd.max',
					'avg'		=> 'rsm.slv.rdap.upd.avg',
					'pfailed'	=> 'rsm.slv.rdap.upd.pfailed'
				}
			}
		}
	]
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

my $cfg_max_value = get_macro_rdds_update_time();
my $delay = get_macro_rdds_delay($now);

my ($month_from, $month_till, $value_ts) = get_month_bounds($now, $delay);
my $cycle_till = cycle_end($value_ts, $delay);

my $left_cycles = get_num_cycles($cycle_till + 1, $month_till, $delay);

my $probes_ref = get_probes(ENABLED_RDDS_EPP);

my $rdds43_probe_count = 0;
my $rdap_probe_count = 0;

foreach my $probe (keys(%{$probes_ref}))
{
	my $templateid = get_hostid("Template $probe");

	$rdds43_probe_count++ if (probe_service_enabled($templateid, ENABLED_RDDS43) != 0);
	$rdap_probe_count++ if (probe_service_enabled($templateid, ENABLED_RDAP) != 0);
}

my $probe_times_ref = get_probe_times($month_from, $cycle_till, $probes_ref);

my $tlds_ref;
if (opt('tld'))
{
	fail("TLD ", getopt('tld'), " does not exist.") if (tld_exists(getopt('tld')) == 0);

	$tlds_ref = [ getopt('tld') ];
}
else
{
	$tlds_ref = get_tlds(ENABLED_RDDS_EPP);
}

if (opt('debug'))
{
	dbg("delay: ", friendly_delay($delay));
	dbg("period: ", selected_period($month_from, $cycle_till), ", value ts: ", ts_full($value_ts));
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

		process_slv_monthly($tld, $month_from, $cycle_till, $value_ts, $delay, $probe_times_ref, $keys,
				'RDDS Update Time', \&check_item_value, \&max_tests);

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

sub max_tests
{
	my $tests_performed = shift;
	my $service = shift;

	my $probe_count;;

	if ($service eq 'RDDS43')
	{
		$probe_count = $rdds43_probe_count;
	}
	elsif ($service eq 'RDAP')
	{
		$probe_count = $rdap_probe_count;
	}
	else
	{
		fail("internal error: unkown service: \"$service\"");
	}

	return $tests_performed + ($left_cycles * $probe_count);
}
