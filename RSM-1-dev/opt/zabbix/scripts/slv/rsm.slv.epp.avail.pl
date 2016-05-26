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
use Alerts;
use Parallel;

my $cfg_key_in = 'rsm.epp[{$RSM.TLD}';
my $cfg_key_out = 'rsm.slv.epp.avail';

parse_avail_opts('now=i');
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my $delay = get_macro_epp_delay();
my $cfg_minonline = get_macro_epp_probe_online();
my $probe_avail_limit = get_macro_probe_avail_limit();

my $now = (opt('now') ? getopt('now') : time());
my $cycles = (opt('cycles') ? getopt('cycles') : 1);

my $max_avail_time = max_avail_time($delay);

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

my $times_from = get_cycle_bounds($now - $delay, $delay);
my $times_till = ($times_from + $delay + $delay * $cycles - 1);
my $probe_times_ref = get_probe_times($times_from, $times_till);

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
			if (!($itemid = get_itemid_by_host($tld, $cfg_key_out)))
			{
				wrn("configuration error: ", rsm_slv_error());
				exit(0);
			}
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

			my $result = process_slv_avail($tld, $cfg_key_in, $from, $till, $cfg_minonline, $probe_times_ref,
				\&check_item_values);

			if ($result)
			{
				my $value = $result->{'value'};
				my $message = $result->{'message'};
				my $alert = $result->{'alert'};


				push_value($tld, $cfg_key_out, $value_ts, $value, $message);

				add_alert(ts_str($value_ts) . "#system#zabbix#$cfg_key_out#PROBLEM#$tld ($message)") if ($alert);
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

# SUCCESS - no values or at least one successful value
# E_FAIL  - all values unsuccessful
sub check_item_values
{
	my $value = shift;

	return SUCCESS if (!defined($value));

	return SUCCESS if ($value == UP);

	return E_FAIL;
}
