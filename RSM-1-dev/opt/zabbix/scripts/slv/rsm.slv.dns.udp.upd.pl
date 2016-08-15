#!/usr/bin/perl
#
# UDP DNS Update Time

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
	'services' =>
	[
		{
			'service' => 'DNS',
			'keys' =>
			{
				'in' => 'rsm.dns.udp.upd[{$RSM.TLD},',
				'out' =>
				{
					'failed'	=> 'rsm.slv.dns.udp.upd.failed',
					'max'		=> 'rsm.slv.dns.udp.upd.max',
					'avg'		=> 'rsm.slv.dns.udp.upd.avg',
					'pfailed'	=> 'rsm.slv.dns.udp.upd.pfailed'
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

my $cfg_max_value = get_macro_dns_update_time();
my $delay = get_macro_dns_udp_delay($now);

my ($month_from, $month_till, $value_ts) = get_month_bounds($now, $delay);
my $cycle_till = cycle_end($value_ts, $delay);

my $left_cycles = get_num_cycles($cycle_till + 1, $month_till, $delay);
my ($probes_ipv4, $probes_ipv6) = get_ipv_probes();

my $left_ipv4_cycles = $left_cycles * $probes_ipv4;
my $left_ipv6_cycles = $left_cycles * $probes_ipv6;

my $probes_ref = get_probes(ENABLED_EPP);
my $probe_times_ref = get_probe_times($month_from, $cycle_till, $probes_ref);

my $tlds_ref;
if (opt('tld'))
{
        fail("TLD ", getopt('tld'), " does not exist.") if (tld_exists(getopt('tld')) == 0);

        $tlds_ref = [ getopt('tld') ];
}
else
{
        $tlds_ref = get_tlds(ENABLED_EPP);
}

if (opt('debug'))
{
	dbg("$probes_ipv4 probes with IPv4 enabled");
	dbg("$probes_ipv6 probes with IPv6 enabled");
	dbg("$left_cycles month cycles left");
	dbg("delay: ", friendly_delay($delay));
	dbg("period: ", selected_period($month_from, $cycle_till), ", value ts: ", ts_full($value_ts));
}

my ($ipv4_addresses, $ipv6_addresses);

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

		# NB! These variables are global and are used in max_tests().
		$ipv4_addresses = 0;
		$ipv6_addresses = 0;

		my $key_in = $keys->{'services'}->[0]->{'keys'}->{'in'};
		my $nsips_ref = get_templated_nsips($tld, $key_in);

		foreach my $nsip (@$nsips_ref)
		{
			my $ip_version = get_ip_version(get_ip_from_nsip($nsip));

			if ($ip_version == 4)
			{
				$ipv4_addresses++;
			}
			else
			{
				$ipv6_addresses++;
			}
		}

		process_slv_monthly($tld, $month_from, $cycle_till, $value_ts, $delay, $probe_times_ref, $keys,
			'UDP DNS Update Time', \&check_item_value, \&max_tests);

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

	return $tests_performed + ($left_ipv4_cycles * $ipv4_addresses) + ($left_ipv6_cycles * $ipv6_addresses);
}
