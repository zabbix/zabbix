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

my $cfg_key_in = 'rsm.dns.udp.rtt[{$RSM.TLD},';
my $cfg_key_out = 'rsm.slv.dns.ns.avail[';
my $cfg_key_out_md = 'rsm.slv.dns.ns.downtime[';	# monthly downtime in minutes

parse_avail_opts('now=i');
exit_if_running();

my $now;
if (opt('now'))
{
	$now = getopt('now');

	setopt('nolog');
	setopt('dry-run');
}
else
{
	$now = time();
}

set_slv_config(get_rsm_config());

db_connect();

my $interval = get_macro_dns_udp_delay($now);
my $cfg_minonline = get_macro_dns_probe_online();
my $cfg_max_value = get_macro_dns_udp_rtt_high();
my $probe_avail_limit = get_macro_probe_avail_limit();

my ($from, $till, $value_ts) = get_interval_bounds($interval, $now);

my $tlds_ref = get_tlds();
my @tlds;

foreach (@$tlds_ref)
{
	$tld = $_; # set global variable here

	my $result;

	if (get_lastclock($tld, $cfg_key_out, \$result) != SUCCESS)
	{
		wrn("configuration error: DNS NS availability items not found (\"$cfg_key_out*\")");
		next;
	}

	if (avail_value_exists($value_ts, $result->{'itemid'}) == SUCCESS)
	{
		# value already exists
		next unless (opt('dry-run'));
	}

	push(@tlds, $tld);
}

$tld = undef;

my $tld_index = 0;
my $tld_count = scalar(@tlds);

my $cycleclock = cycle_start($value_ts, $interval);

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
		$tld = $tlds[$tld_index];

		init_values();

		db_connect();

		my $values = process_slv_ns_avail($tld, $cfg_key_in, $cfg_key_out, $cfg_key_out_md, $from, $till, $value_ts,
			$cfg_minonline, $probe_avail_limit, \&check_item_value);

		if ($values == 0)
		{
			wrn("no DNS UDP test results found in the database (cycle: ", ts_full(cycle_start($value_ts, $interval)), ")");
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
