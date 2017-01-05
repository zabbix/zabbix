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

parse_opts('tld=s', 'from=n', 'till=n');

setopt('nolog');
setopt('dry-run');

$tld = getopt('tld');
my $from = getopt('from');
my $till = getopt('till');

usage() unless ($tld && $from && $till);

set_slv_config(get_rsm_config());

db_connect();

my $rows_ref = db_select(
	"select i.key_,hi.clock,hi.value".
	" from items i,hosts h,history_uint hi".
	" where h.host='$tld'".
		" and i.hostid=h.hostid".
		" and i.itemid=hi.itemid".
		" and i.key_ like 'rsm.slv.%'".
		" and hi.clock between $from and $till".
        " order by hi.clock,hi.ns");

printf("%-30s %-40s %s\n", "KEY", "CLOCK", "VALUE");
print("----------------------------------------------------------------------------------------------------\n");
foreach my $row_ref (@$rows_ref)
{
	my $key = $row_ref->[0];
	my $clock = $row_ref->[1];
	my $value = $row_ref->[2];

	printf("%-30s %-40s %s\n", $key, ts_full($clock), $value);
}

__END__

=head1 NAME

slv-results.pl - show accumulated results stored by cron

=head1 SYNOPSIS

slv-results.pl --tld <tld> --from <unixtime> --till <unixtime> [options] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--tld> tld

Show results of specified TLD.

=item B<--from> timestamp

Specify Unix timestamp within the cycle.

=item B<--till> timestamp

Specify Unix timestamp within the cycle.

=item B<--debug>

Run the script in debug mode. This means printing more information.

=item B<--help>

Print a brief help message and exit.

=back

=head1 DESCRIPTION

B<This program> will show results of a TLD stored by cron job.

=head1 EXAMPLES

./slv-results.pl --tld example --from $(date +%s -d '-1 day') --till $(date +%s -d '-1 day + 59 seconds')

This will update API data of the last 10 minutes of DNS, DNSSEC, RDDS and EPP services of TLD example.

=cut
