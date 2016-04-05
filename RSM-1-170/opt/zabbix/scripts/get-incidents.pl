#!/usr/bin/perl -w

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use RSM;
use RSMSLV;

parse_opts("tld=s", "from=n", "till=n", "failed!");

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
		if (!$itemid)
		{
			wrn("configuration error: ", rsm_slv_error());
			next;
		}

		my $incidents = get_incidents($itemid, $from, $till);

		foreach (@$incidents)
		{
			my $eventid = $_->{'eventid'};
			my $start = $_->{'start'};
			my $end = $_->{'end'};
			my $false_positive = $_->{'false_positive'};

			my $failed_tests = "";

			if (opt('failed'))
			{
				my $time_condition = defined($end) ? "clock between $start and $end" : "clock>=$start";

				my $rows_ref = db_select(
					"select count(*)".
					" from history_uint".
					" where itemid=$itemid".
						" and value=".DOWN.
						" and ".sql_time_condition($start, $end));

				$failed_tests = ',' . $rows_ref->[0]->[0];
			}

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
			print("$eventid,$tld,$service,$status," . ts_full($start) . ",", (defined($end) ? ts_full($end) : ""), "$failed_tests\n");
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

get-incidents.pl [--tld tld] [--from timestamp] [--till timestamp] [--failed] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--tld> tld

Specify TLD. By default incidents of all TLDs available in the system are displayed.

=item B<--from> timestamp

Optionally specify the beginning of period for getting incidents. In case of ongoing
incident at the specified time it will be displayed with full details.

=item B<--till> timestamp

Optionally specify the end of period for getting incidents.

=item B<--failed>

Include number if failed tests within incident in the output.

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
