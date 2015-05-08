#!/usr/bin/perl -w

use lib '/opt/zabbix/scripts';

use strict;
use RSM;
use RSMSLV;

parse_opts("tld=s", "from=n", "till=n");

# do not write any logs
setopt('nolog');

if (opt('debug'))
{
	dbg("command-line parameters:");
	dbg("$_ => ", getopt($_)) foreach (optkeys());
}

set_slv_config(get_rsm_config());

db_connect();

my $from = getopt('from');
my $till = getopt('till');

my $tlds_ref = opt('tld') ? [ getopt('tld') ] : get_tlds();

foreach (@$tlds_ref)
{
	$tld = $_;

	foreach my $service ('dns', 'rdds', 'epp')
	{
		next unless (SUCCESS == tld_service_enabled($tld, $service));

		my $key = "rsm.slv.$service.avail";

		my $itemid = get_itemid_by_host($tld, $key);
		my $incidents = get_incidents($itemid, $from, $till);

		foreach (@$incidents)
		{
			my $eventid = $_->{'eventid'};
			my $start = $_->{'start'};
			my $end = $_->{'end'};
			my $false_positive = $_->{'false_positive'};

			my $time_condition = defined($end) ? "clock between $start and $end" : "clock>=$start";

			my $rows_ref = db_select(
				"select count(*)".
				" from history_uint".
				" where itemid=$itemid".
					" and value=".DOWN.
					" and ".sql_time_condition($start, $end));

			my $failed_tests = $rows_ref->[0]->[0];

			my $status;
			if ($false_positive != 0)
			{
				$status = 'FALSE POSITIVE';
			}
			elsif (not defined($end))
			{
				$status = 'ACTIVE';
			}
			else
			{
				$status = 'RESOLVED';
			}

			# "IncidentID,TLD,Status,StartTime,EndTime,FailedTestsWithinIncident"
			print("$eventid,$tld,$service,$status,$start,", (defined($end) ? $end : ""), ",$failed_tests\n");
		}
	}
}

# unset TLD (for the logs)
$tld = undef;

slv_exit(SUCCESS);

__END__

=head1 NAME

get-incidents.pl - display incidents as a comma-separated list of details:

IncidentID,TLD,Status,StartTime,EndTime,FailedTestsWithinIncident

=head1 SYNOPSIS

get-incidents.pl [--tld tld] [--from timestamp] [--till timestamp] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--tld> tld

Specify TLD. By default incidents of all TLDs available in the system are displayed.

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

B<This program> will display incidents of all TLDs as a comma-separated list of details.
You can specify a single TLD for processing. Also you may specify time bounds of period
for getting incidents.

=cut
