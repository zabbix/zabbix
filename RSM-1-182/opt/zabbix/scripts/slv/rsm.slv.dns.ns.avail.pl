#!/usr/bin/perl
#
# DNS NS availability

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
use Alerts;

my $cfg_key_in = 'rsm.dns.udp.rtt[{$RSM.TLD},';
my $cfg_key_out = 'rsm.slv.dns.ns.avail[';

parse_avail_opts();
exit_if_running();

my $now = (opt('now') ? getopt('now') : time());
my $cycles = (opt('cycles') ? getopt('cycles') : 1);

set_slv_config(get_rsm_config());

db_connect();

my $delay = get_macro_dns_udp_delay($now);
my $cfg_minonline = get_macro_dns_probe_online();
my $cfg_max_value = get_macro_dns_udp_rtt_high();
my $max_avail_time = max_avail_time($delay);

my $tlds_ref;
if (opt('tld'))
{
	fail("TLD ", getopt('tld'), " does not exist.") if (tld_exists(getopt('tld')) == 0);

	$tlds_ref = [ getopt('tld') ];
}
else
{
	$tlds_ref = get_tlds(ENABLED_DNS);
}

my $times_from = get_cycle_bounds($now - $delay, $delay);
my $times_till = ($times_from + $delay + $delay * $cycles - 1);
my $probes_ref = get_probes(ENABLED_DNS);
my $probe_times_ref = get_probe_times($times_from, $times_till, $probes_ref);

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

		my $itemid;
		if (!opt('dry-run'))
		{
			my $result;

			if (get_lastclock($tld, $cfg_key_out, \$result) != SUCCESS)
			{
				wrn("configuration error: DNS NS availability items not found (\"$cfg_key_out*\")");
				exit(0);
			}

			$itemid = $result->{'itemid'};
		}

		init_values();

		while ($cycles > 0)
		{
			my ($from, $till, $value_ts) = get_cycle_bounds($now, $delay);

			$cycles--;
			$now += $delay;

			if (!opt('dry-run'))
			{
				next if (uint_value_exists($value_ts, $itemid) == SUCCESS);
			}

			next if ($till > $max_avail_time);

			my $result = process_slv_ns_avail($tld, $cfg_key_in, $from, $till, $cfg_minonline,
				$probe_times_ref, \&check_item_value);

			if (scalar(keys(%{$result})) == 0)
			{
				wrn("no DNS NS UDP test results found in the database (cycle: ", ts_full(cycle_start($value_ts, $delay)), ")");
			}
			else
			{
				foreach my $nsip (keys(%$result))
				{
					my $key = $cfg_key_out . $nsip . ']';
					my $value = $result->{$nsip}->{'value'};
					my $message = $result->{$nsip}->{'message'};
					my $alert = $result->{$nsip}->{'alert'};

					push_value($tld, $key, $value_ts, $value, $message);

					add_alert(ts_str($value_ts) . "#system#zabbix#$key#PROBLEM#$tld ($message)") if ($alert);
				}
			}
		}

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
