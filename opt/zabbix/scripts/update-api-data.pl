#!/usr/bin/perl -w

use lib '/opt/zabbix/scripts';

use strict;
use RSM;
use RSMSLV;
use ApiHelper;
use Data::Dumper;

parse_opts("tld=s", "service=s", "from=n", "till=n");

# do not write any logs
$OPTS{'test'} = 1;

if (defined($OPTS{'debug'}))
{
    dbg("command-line parameters:");
    dbg("$_ => ", $OPTS{$_}) foreach (keys(%OPTS));
}

my @services;
if (defined($OPTS{'service'}))
{
    push(@services, lc($OPTS{'service'}));
}
else
{
    push(@services, 'dns', 'rdds', 'epp');
}

set_slv_config(get_rsm_config());

db_connect();

my $from = $OPTS{'from'};
my $till = $OPTS{'till'};

my $tlds_ref = defined($OPTS{'tld'}) ? [ $OPTS{'tld'} ] : get_tlds();

foreach (@$tlds_ref)
{
    $tld = $_;

    foreach my $service (@services)
    {
	if (tld_service_enabled($tld, $service) != SUCCESS)
	{
	    if (ah_save_alarmed($tld, $service, AH_ALARMED_DISABLED) != AH_SUCCESS)
	    {
		fail("cannot save alarmed: ", ah_get_error());
	    }
	    next;
	}

	# working item
	my $key = "rsm.slv.$service.avail";
	my $itemid = get_itemid_by_host($tld, $key);

	my ($rollweek_from, $rollweek_till) = get_rollweek_bounds();

	my $downtime = get_downtime($itemid, $rollweek_from, $rollweek_till);

	if (AH_SUCCESS != ah_save_service_availability($tld, $service, $downtime))
        {
            fail("cannot save service availability: ", ah_get_error());
        }

	# get availability
	my $now = time();
	my $incidents = get_incidents($itemid, $now);

	my $alarmed_status = AH_ALARMED_NO;
	if (scalar(@$incidents) != 0)
	{
	    if ($incidents->[0]->{'false_positive'} != 0 and not defined($incidents->[0]->{'end'}))
	    {
		$alarmed_status = AH_ALARMED_YES;
	    }
	}

	if (AH_SUCCESS != ah_save_alarmed($tld, $service, $alarmed_status))
	{
	    fail("cannot save alarmed: ", ah_get_error());
	}

	$incidents = get_incidents($itemid, $from, $till);

	foreach (@$incidents)
	{
	    my $eventid = $_->{'eventid'};
	    my $start = $_->{'start'};
	    my $end = $_->{'end'};
	    my $false_positive = $_->{'false_positive'};

	    if (AH_SUCCESS != ah_save_incident($tld, $OPTS{'service'}, $eventid, $start, $end, $false_positive))
	    {
		fail("cannot save incident: ", ah_get_error());
	    }
	}
    }
}

# unset TLD (for the logs)
$tld = undef;

slv_exit(SUCCESS);

__END__

=head1 NAME

update-api-data.pl - save information about the incidents to a filesystem

=head1 SYNOPSIS

update-api-data.pl --service <dns|rdds|epp> [--tld tld] [--from timestamp] [--till timestamp] [--test] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--service> name

Specify the name of the service: dns, rdds or epp.

=item B<--tld> tld

Process only specified TLD. By default all TLDs will be processed.

=item B<--from> timestamp

Optionally specify the beginning of period for getting incidents. In case of ongoing                                                                                                                                                          
incident at the specified time it will be displayed with full details.

=item B<--till> timestamp

Optionally specify the end of period for getting incidents.                               

=item B<--debug>

Run the script in debug mode. This means printing more information.

=item B<--help>

Print a brief help message and exit.

=back

=head1 DESCRIPTION

B<This program> will run through all the incidents found at optionally specified time bounds
and store details about each on the filesystem. This information will be used by external
program to provide it for users in convenient way.

=cut
