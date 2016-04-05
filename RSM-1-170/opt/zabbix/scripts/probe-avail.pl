#!/usr/bin/perl -w

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;
use Data::Dumper;

parse_opts('from=n', 'period=n', 'service=s', 'probe=s');

# do not write any logs
setopt('nolog');

if (opt('debug'))
{
	dbg("command-line parameters:");
	dbg("$_ => ", getopt($_)) foreach (optkeys());
}

set_slv_config(get_rsm_config());

db_connect();

__validate_input();

my $opt_from = getopt('from');

if (defined($opt_from))
{
	$opt_from = truncate_from($opt_from);	# use the whole minute
	dbg("option \"from\" truncated to the start of a minute: $opt_from") if ($opt_from != getopt('from'));
}

my %services;
if (opt('service'))
{
	$services{lc(getopt('service'))} = undef;
}
else
{
	foreach my $service ('dns', 'dnssec', 'rdds', 'epp')
	{
		$services{$service} = undef;
	}
}

my $cfg_dns_delay = undef;
my $cfg_dns_valuemaps;

my $cfg_dns_statusmaps = get_statusmaps('dns');

foreach my $service (keys(%services))
{
	if ($service eq 'dns' || $service eq 'dnssec')
	{
		if (!$cfg_dns_delay)
		{
			$cfg_dns_delay = get_macro_dns_udp_delay();
			$cfg_dns_valuemaps = get_valuemaps('dns');
		}

		$services{$service}{'delay'} = $cfg_dns_delay;
		$services{$service}{'valuemaps'} = $cfg_dns_valuemaps;
		$services{$service}{'key_status'} = 'rsm.dns.udp[{$RSM.TLD}]'; # 0 - down, 1 - up
		$services{$service}{'key_rtt'} = 'rsm.dns.udp.rtt[{$RSM.TLD},';
	}

	if ($service eq 'rdds')
	{
		$services{$service}{'delay'} = get_macro_rdds_delay();
		$services{$service}{'valuemaps'} = get_valuemaps($service);
		$services{$service}{'key_status'} = 'rsm.rdds[{$RSM.TLD}'; # 0 - down, 1 - up, 2 - only 43, 3 - only 80
		$services{$service}{'key_43_rtt'} = 'rsm.rdds.43.rtt[{$RSM.TLD}]';
		$services{$service}{'key_43_ip'} = 'rsm.rdds.43.ip[{$RSM.TLD}]';
		$services{$service}{'key_43_upd'} = 'rsm.rdds.43.upd[{$RSM.TLD}]';
		$services{$service}{'key_80_rtt'} = 'rsm.rdds.80.rtt[{$RSM.TLD}]';
		$services{$service}{'key_80_ip'} = 'rsm.rdds.80.ip[{$RSM.TLD}]';

	}

	if ($service eq 'epp')
	{
		$services{$service}{'delay'} = get_macro_epp_delay();
		$services{$service}{'valuemaps'} = get_valuemaps($service);
		$services{$service}{'key_status'} = 'rsm.epp[{$RSM.TLD},'; # 0 - down, 1 - up
		$services{$service}{'key_ip'} = 'rsm.epp.ip[{$RSM.TLD}]';
		$services{$service}{'key_rtt'} = 'rsm.epp.rtt[{$RSM.TLD},';
	}
}

my $probe_avail_limit = get_macro_probe_avail_limit();

my ($check_from, $check_till, $continue_file);

$check_from = $opt_from;
$check_till = $check_from + getopt('period') * 60 - 1;

if ($check_till > time())
{
	fail("specified period (", selected_period($check_from, $check_till), ") is in the future");
}

my ($from, $till) = get_real_services_period(\%services, $check_from, $check_till);

if (!$from)
{
    info("no full test periods within specified time range: ", selected_period($check_from, $check_till));
    exit(0);
}

dbg(sprintf("getting probe statuses for period: %s", selected_period($from, $till)));

my $all_probes_ref;

if (opt('probe'))
{
	$all_probes_ref = get_probes(undef, getopt('probe'));
}
else
{
	$all_probes_ref = get_probes(undef);
}

my $probe_times_ref = get_probe_times($from, $till, $probe_avail_limit, $all_probes_ref);

print("Status of Probes at ", ts_str(getopt('from')), "\n");
print("---------------------------------------\n");
foreach my $probe (keys(%$probe_times_ref))
{
	my $offline = probe_offline_at($probe_times_ref, $probe, getopt('from'));

	if ($offline == 0)
	{
		print("$probe: ", PROBE_ONLINE_STR, "\n");
	}
	else
	{
		print("$probe: ", PROBE_OFFLINE_STR, "\n");
	}
}

#print(Dumper($probe_times_ref));

sub __validate_input
{
	if (!opt('from') || !opt('period'))
	{
		usage();
	}

	if (opt('service'))
	{
		if (getopt('service') ne 'dns' and getopt('service') ne 'dnssec' and getopt('service') ne 'rdds' and getopt('service') ne 'epp')
		{
			print("Error: \"", getopt('service'), "\" - unknown service\n");
			usage();
		}
	}

	if (opt('probe'))
	{
		my $probe = getopt('probe');

		my $probes_ref = get_probes();
		my $valid = 0;

		foreach my $name (keys(%$probes_ref))
		{
			if ($name eq $probe)
			{
				$valid = 1;
				last;
			}
		}

		if ($valid == 0)
		{
			print("Error: unknown probe \"$probe\"\n");
			print("\nAvailable probes:\n");
			foreach my $name (keys(%$probes_ref))
			{
				print("  $name\n");
			}
			exit(E_FAIL);
		}
        }
}


__END__

=head1 NAME

probe-avail.pl - get information about Probe availability at a specified period

=head1 SYNOPSIS

probe-avail.pl --from <timestamp> --period <minutes> [--service <dns|dnssec|rdds|epp>] [--probe <probe>] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--from> timestamp

Specify Unix timestamp within the oldest test cycle to handle in this run. You don't need to specify the
first second of the test cycle, any timestamp within it will work. Number of test cycles to handle within
this run can be specified using option --period otherwise all completed test cycles available in the
database up till now will be handled.

=item B<--period> minutes

Specify number minutes of the period to handle during this run. The first cycle to handle can be specified
using options --from or --continue (continue from the last time when --continue was used) (see below).

=item B<--service> service

Process only specified service. Service must be one of: dns, dnssec, rdds or epp.

=item B<--probe> name

Process only specified probe.

=item B<--debug>

Run the script in debug mode. This means printing more information.

=item B<--help>

Print a brief help message and exit.

=back

=head1 DESCRIPTION

B<This program> will print information about Probe availability at a specified period.

=head1 EXAMPLES

./probe-avail.pl --from 1443015000 --period 10

This will output Probe availability for all service tests that fall under period 23.09.2015 16:30:00-16:40:00 .

=cut
