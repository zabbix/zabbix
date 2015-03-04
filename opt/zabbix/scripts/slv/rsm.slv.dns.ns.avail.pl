#!/usr/bin/perl
#
# DNS NS availability

use lib '/opt/zabbix/scripts';

use strict;
use warnings;
use RSM;
use RSMSLV;
use Parallel;

my $cfg_key_in = 'rsm.dns.udp.rtt[{$RSM.TLD},';
my $cfg_key_out = 'rsm.slv.dns.ns.avail[';

parse_opts();
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my $interval = get_macro_dns_udp_delay();
my $cfg_minonline = get_macro_dns_probe_online();
my $cfg_max_value = get_macro_dns_udp_rtt_high();
my $probe_avail_limit = get_macro_probe_avail_limit();

my ($from, $till, $value_ts) = get_interval_bounds($interval);

my $tlds_ref = get_tlds();
my @tlds;

foreach (@$tlds_ref)
{
	$tld = $_; # set global variable here

	my $lastclock = get_lastclock($tld, $cfg_key_out);
	next if (check_lastclock($lastclock, $value_ts, $interval) != SUCCESS);

	push(@tlds, $tld);
}

$tld = undef;

my $tld_index = 0;
my $tld_count = scalar(@tlds);

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

		process_slv_ns_avail($tld, $cfg_key_in, $cfg_key_out, $from, $till, $value_ts, $cfg_minonline,
			$probe_avail_limit, \&check_item_value);

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
