#!/usr/bin/perl
#
# DNS NS downtime of current month in minutes

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

my $cfg_key_in = 'rsm.slv.dns.ns.avail[';
my $cfg_key_out = 'rsm.slv.dns.ns.downtime[';

parse_opts();
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my $now = (opt('now') ? getopt('now') : time());

my $delay = get_macro_dns_udp_delay($now);

my ($month_from, undef, $value_ts) = get_month_bounds($now, $delay);
my $month_till = cycle_end($value_ts, $delay);

my $tlds_ref = get_tlds();

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

		my $tld_items;

		my $nsips_ref = get_nsips($tld, $cfg_key_in);

		foreach my $nsip (@$nsips_ref)
		{
			my $itemid_in = get_itemid_by_host($tld, "$cfg_key_in$nsip]");

			next unless ($itemid_in);

			if (!opt('dry-run'))
			{
				my $itemid_out = get_itemid_by_host($tld, "$cfg_key_out$nsip]");

				next if (uint_value_exists($value_ts, $itemid_out) == SUCCESS);
			}

			$tld_items->{$nsip} = $itemid_in;
		}

		init_values();

		foreach my $nsip (@{$nsips_ref})
		{
			my $downtime = get_downtime($tld_items->{$nsip}, $month_from, $month_till, 1);	# ignore incidents

			push_value($tld, "$cfg_key_out$nsip]", $value_ts, $downtime, "$downtime minutes of downtime from ",
				ts_full($month_from), " till ", ts_full($month_till));
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
