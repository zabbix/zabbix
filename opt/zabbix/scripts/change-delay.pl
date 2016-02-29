#!/usr/bin/perl -w

use lib '/opt/zabbix/scripts';

use RSM;
use RSMSLV;
use Getopt::Long;

set_slv_config(get_rsm_config());

parse_opts('type=n', 'delay=n');
usage() unless (__validate_input() == SUCCESS);

my ($key_part, $macro, $sql);
if (getopt('type') == 1)
{
	$key_part = 'rsm.dns.udp[%';
	$macro = '{$RSM.DNS.UDP.DELAY}';
}
elsif (getopt('type') == 2)
{
	$key_part = 'rsm.dns.tcp[%';
	$macro = '{$RSM.DNS.TCP.DELAY}';
}
elsif (getopt('type') == 3)
{
	$key_part = 'rsm.rdds[%';
	$macro = '{$RSM.RDDS.DELAY}';
}
elsif (getopt('type') == 4)
{
	$key_part = 'rsm.epp[%';
	$macro = '{$RSM.EPP.DELAY}';
}

if (opt('dry-run'))
{
	print("would set delay ", getopt('delay'), " for keys like $key_part\n");
	print("would set macro $macro to ", getopt('delay'), "\n");
	exit;
}

db_connect();

$sql = "update items set delay=? where type=3 and key_ like ?";
$sth = $dbh->prepare($sql) or die $dbh->errstr;
$sth->execute(getopt('delay'), $key_part) or die $dbh->errstr;

$sql = "update globalmacro set value=? where macro=?";
$sth = $dbh->prepare($sql) or die $dbh->errstr;
$sth->execute(getopt('delay'), $macro) or die $dbh->errstr;

sub __validate_input
{
	return E_FAIL unless (getopt('type') and getopt('delay'));
	return E_FAIL unless (getopt('type') >= 1 and getopt('type') <= 4);
	return E_FAIL unless (getopt('delay') >= 60 and getopt('delay') <= 3600);

	return SUCCESS;
}

__END__

=head1 NAME

change-delay.pl - change delay of a particular service

=head1 SYNOPSIS

change-delay.pl --type <1-4> --delay <60-3600> [--dry-run] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--type> number

Specify number of the service: 1 - DNS UDP, 2 - DNS TCP, 3 - RDDS, 4 - EPP .

=item B<--delay> number

Specify seconds if delay between tests. Allowed values between 60 and 3600. Use full minutes (e. g. 60, 180, 600).

=item B<--dry-run>

Print data to the screen, do not change anything in the system.

=item B<--debug>

Run the script in debug mode. This means printing more information.

=item B<--help>

Print a brief help message and exit.

=back

=head1 DESCRIPTION

B<This program> will change the delay between particuar test in the system.

=head1 EXAMPLES

./change-delay.pl --type 2 --delay 120

This will set the delay between DNS TCP tests to 120 seconds.

=cut
