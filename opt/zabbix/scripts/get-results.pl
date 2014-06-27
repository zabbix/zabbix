#!/usr/bin/perl -w

use strict;
use warnings;

use lib '/opt/zabbix/scripts';
use RSM;
use RSMSLV;

parse_opts("tld=s", "from=n", "till=n", "service=s");

if (defined($OPTS{'debug'}))
{
    dbg("command-line parameters:");
    dbg("$_ => ", $OPTS{$_}) foreach (keys(%OPTS));
}

unless (defined($OPTS{'service'}))
{
    print("Option --service not specified\n");
    usage(2);
}

set_slv_config(get_rsm_config());

db_connect();

my ($key, $cfg_max_value, $service_type);

if ($OPTS{'service'} eq 'tcp-dns-rtt')
{
    $key = 'rsm.dns.tcp.rtt[{$RSM.TLD},';
    $cfg_max_value = get_macro_dns_tcp_rtt_low();
}
elsif ($OPTS{'service'} eq 'udp-dns-rtt')
{
    $key = 'rsm.dns.udp.rtt[{$RSM.TLD},';
    $cfg_max_value = get_macro_dns_udp_rtt_low();
}
elsif ($OPTS{'service'} eq 'dns-upd')
{
    $key = 'rsm.dns.udp.upd[{$RSM.TLD},';
    $cfg_max_value = get_macro_dns_update_time();
    $service_type = 'rdds';
}
else
{
    print("Invalid name of service specified \"", $OPTS{'service'}, "\"\n");
    usage(2);
}

my $from = $OPTS{'from'};
my $till = $OPTS{'till'};
my $value_ts = $till;

unless (defined($from) and defined($till))
{
    dbg("getting current month bounds");
    my @bounds = get_curmon_bounds();

    $from = $bounds[0] unless (defined($from));
    $till = $bounds[1] unless (defined($till));
    $value_ts = $value_ts;
}

my $probe_avail_limit = get_macro_probe_avail_limit();

my $probe_times_ref = get_probe_times($from, $till, $probe_avail_limit);

my $tlds_ref = defined($OPTS{'tld'}) ? [ $OPTS{'tld'} ] : get_tlds($service_type);

foreach (@$tlds_ref)
{
    $tld = $_;

    if ("," eq substr($key, -1))
    {
	my $result = get_ns_results($tld, $key, $value_ts, $probe_times_ref, \&check_item_value);

	foreach my $ns (keys(%$result))
	{
	    my $total = $result->{$ns}->{'total'};
	    my $successful = $result->{$ns}->{'successful'};

	    if ($total == 0)
	    {
		info("$ns: no results found in the database from ", ts_str($from), " ($from) till ", ts_str($till), " ($till)");
		next;
	    }

	    info("$ns: $successful/$total successful results from ", ts_str($from), " ($from) till ", ts_str($till), " ($till)");
	}
    }
}

# unset TLD (for the logs)
$tld = undef;

slv_exit(SUCCESS);

sub check_item_value
{
    my $value = shift;

    return (is_service_error($value) == SUCCESS or $value > $cfg_max_value) ? FAIL : SUCCESS;
}

__END__

=head1 NAME

get-results.pl - get successful/total test results of the service for given period of time

=head1 SYNOPSIS

get-results.pl --service <tcp-dns-rtt|udp-dns-rtt> [--tld tld] [--from timestamp] [--till timestamp] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--service> name

Specify the name of the service. Supported services: tcp-dns-rtt, udp-dns-rtt.

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

B<This program> will return number of successful and total results of the service
of a specified tld or by default for all available tlds in the system. By default
the period of calculation is current month.

=cut
