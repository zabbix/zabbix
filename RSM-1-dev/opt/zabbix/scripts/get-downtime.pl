#!/usr/bin/perl

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;

parse_opts('tld=s', 'from=n', 'till=n', 'service=s');

setopt('nolog');

if (opt('debug'))
{
	dbg("command-line parameters:");
	dbg("$_ => ", getopt($_)) foreach (optkeys());
}

unless (opt('service'))
{
	print("Option --service not specified\n");
	usage(2);
}

set_slv_config(get_rsm_config());

db_connect();

my ($key, $service_option, $delay);

if (getopt('service') eq 'dns')
{
	$service_option = ENABLED_DNS;
	$key = 'rsm.slv.dns.avail';
	$delay = get_macro_dns_udp_delay();
}
elsif (getopt('service') eq 'dns-ns')
{
	$service_option = ENABLED_DNS;
	$key = 'rsm.slv.dns.ns.avail[';
	$delay = get_macro_dns_udp_delay();
}
elsif (getopt('service') eq 'rdds')
{
	$service_option = ENABLED_RDDS;
	$key = 'rsm.slv.rdds.avail';
	$delay = get_macro_rdds_delay();
}
elsif (getopt('service') eq 'epp')
{
	$service_option = ENABLED_EPP;
	$key = 'rsm.slv.epp.avail';
	$delay = get_macro_epp_delay();
}
else
{
	print("Invalid service specified \"", getopt('service'), "\"\n");
	usage(2);
}

my ($from, $till) = get_default_period(time() - $delay - AVAIL_SHIFT_BACK, $delay, getopt('from'), getopt('till'));

info('selected period: ', selected_period($from, $till));

my $tlds_ref = opt('tld') ? [ getopt('tld') ] : get_tlds($service_option);

foreach (@$tlds_ref)
{
	$tld = $_;

	if ("[" eq substr($key, -1))
	{
		my $itemids_ref = get_itemids_by_host_and_keypart($tld, $key);
		foreach my $nsip (keys(%$itemids_ref))
		{
			my $itemid = $itemids_ref->{$nsip};
			my $downtime = get_downtime($itemid, $from, $till, 1); # no incidents check

			info("$nsip: $downtime minutes of downtime from ", ts_str($from), " ($from) till ", ts_str($till), " ($till)");
		}
	}
	else
	{
		my $itemid = get_itemid_by_host($tld, $key);
		if (!$itemid)
		{
			wrn("configuration error: service \"", getopt('service'), "\" enabled but item $key not found: ", rsm_slv_error());
		}

		my $downtime = get_downtime($itemid, $from, $till);

		info("$downtime minutes of downtime from ", ts_str($from), " ($from) till ", ts_str($till), " ($till)");
	}
}

# unset TLD (for the logs)
$tld = undef;

slv_exit(SUCCESS);

__END__

=head1 NAME

get-downtime.pl - calculate the downtime of the service for given period of time

=head1 SYNOPSIS

get-downtime.pl --service <dns|dns-ns|rdds|epp> [--tld tld] [--from timestamp] [--till timestamp] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--service> name

Specify the name of the service: dns, dns-ns, rdds or epp.

=item B<--tld> tld

Do the calculation for specified tld. By default the calculation will be done
for all the available tlds in the system.

=item B<--from> timestamp

Specify the beginning of period of calculation. By default the beginning of
current month will be used.

=item B<--till> timestamp

Specify the end of period of calculation. By default the end of current month
will be used.

=item B<--debug>

Run the script in debug mode. This means:

 - skip checks if need to recalculate value
 - do not send the value to the server
 - print the output to stdout instead of writing to the log file
 - print more information

=item B<--help>

Print a brief help message and exit.

=back

=head1 DESCRIPTION

B<This program> will calculate the downtime of the service of a specified tld or
by default for all available tlds in the system. By default the period of
calculation is current month.

=cut
