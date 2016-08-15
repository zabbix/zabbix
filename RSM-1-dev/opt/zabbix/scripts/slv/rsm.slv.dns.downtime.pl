#!/usr/bin/perl
#
# DNS downtime of current month in minutes

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*/.*,$1,; $MYDIR = '..' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;

my $cfg_key_in = 'rsm.slv.dns.avail';
my $cfg_key_out = 'rsm.slv.dns.downtime';

parse_opts();
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my $now = (opt('now') ? getopt('now') : time());

my $delay = get_macro_dns_udp_delay($now);

my ($month_from, undef, $value_ts) = get_month_bounds($now, $delay);
my $month_till = cycle_end($value_ts, $delay);

my $result = process_slv_downtime($month_from, $month_till, $value_ts, $cfg_key_in, $cfg_key_out, get_tlds(ENABLED_DNS));

init_values();

foreach (keys(%{$result}))
{
	$tld = $_;	# set global variable here

	my $downtime = $result->{$tld};

	push_value($tld, $cfg_key_out, $value_ts, $downtime, "$downtime minutes of downtime from ",
		ts_full($month_from), " till ", ts_full($month_till));
}

# unset TLD (for the logs)
$tld = undef;

send_values();

slv_exit(SUCCESS);
