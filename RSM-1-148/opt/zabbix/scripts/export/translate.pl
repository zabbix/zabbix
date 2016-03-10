#!/usr/bin/perl

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
	our $MYDIR2 = $0; $MYDIR2 =~ s,(.*)/.*/.*,$1,; $MYDIR2 = '..' if ($MYDIR2 eq $0);
}
use lib $MYDIR;
use lib $MYDIR2;

use strict;
use warnings;

use DaWa;
use RSM;
use RSMSLV;

use constant PRINT_RIGHT_SHIFT => 30;

parse_opts();

my $data_type = $ARGV[0];
my $data_file = $ARGV[1];
my $data_file_line = $ARGV[2];

my $error = 0;
if (scalar(@ARGV) ne 3)
{
	$error = 1;
}
else
{
	my $found = 0;

	foreach my $id_type (keys(%DATAFILES))
	{
		if ($DATAFILES{$id_type} eq $data_type)
		{
			$found = 1;
			last;
		}
	}

	$error = 1 if ($found == 0);
}

if ($error != 0)
{
	print("usage   : $0 <csv type> <csv file> <line>\n");
	print("example : $0 cycles.csv /tmp/my-cycles-file.csv 1834\n");
	exit(-1);
}

my $fh;
open($fh, '<', $data_file) or die $!;

my $line;
do
{
	$line = <$fh>;
}
until ($. == $data_file_line && defined($line));

print($line);

set_slv_config(get_rsm_config());
db_connect();
dw_csv_init();
dw_load_ids_from_db();

##### TODO: USE MAPPINGS!!!! ######
if ($data_type eq 'cycles.csv')
{
	__translate_cycles_line($line);
}
elsif ($data_type eq 'tests.csv')
{
	__translate_tests_line($line);
}

sub __translate_cycles_line
{
	my $line = shift;

	my @columns = split(',', $line);

	my $cycle_id = dw_translate_cycle_id($columns[0]);
	my $cycle_date_minute = $columns[1];
	my $cycle_emergency_threshold = $columns[2];
	my $cycle_status = dw_get_name(ID_STATUS_MAP, $columns[3]);
	my $incident_id = $columns[4];
	my $cycle_tld = dw_get_name(ID_TLD, $columns[5]);
	my $service_category = dw_get_name(ID_SERVICE_CATEGORY, $columns[6]);
	my $cycle_nsfqdn = dw_get_name(ID_NS_NAME, $columns[7]) || '';
	my $cycle_nsip = dw_get_name(ID_NS_IP, $columns[8]) || '';
	my $cycle_nsipversion = dw_get_name(ID_IP_VERSION, $columns[9]) || '';
	my $tld_type = dw_get_name(ID_TLD_TYPE, $columns[10]);
	my $cycle_protocol = dw_get_name(ID_TRANSPORT_PROTOCOL, $columns[11]);

	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleID', $cycle_id);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleDateMinute', ts_full($cycle_date_minute));
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleEmergencyThreshold', $cycle_emergency_threshold);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleStatus', $cycle_status);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'incidentID', $incident_id);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleTLD', $cycle_tld);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'serviceCategory', $service_category);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleNSFQDN', $cycle_nsfqdn);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleNSIP', $cycle_nsip);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleNSIPVersion', $cycle_nsipversion);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'tldType', $tld_type);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleProtocol', $cycle_protocol);
}

sub __translate_tests_line
{
	my $line = shift;

	my @columns = split(',', $line);

	my $probe_id = dw_get_name(ID_PROBE, $columns[0]);
	my $cycle_date_minute = $columns[1];
	my $test_date_time = $columns[2];
	my $test_rtt = $columns[3];
	my $cycle_id = dw_translate_cycle_id($columns[4]);
	my $test_tld = dw_get_name(ID_TLD, $columns[5]);
	my $test_protocol = dw_get_name(ID_TRANSPORT_PROTOCOL, $columns[6]);
	my $test_ipversion = dw_get_name(ID_IP_VERSION, $columns[7]) || '';
	my $test_ipaddress = dw_get_name(ID_NS_IP, $columns[8]) || '';
	my $test_type = dw_get_name(ID_TEST_TYPE, $columns[9]);
	my $test_nsfqdn = dw_get_name(ID_NS_NAME, $columns[10]) || '';
	my $tld_type = dw_get_name(ID_TLD_TYPE, $columns[11]);

	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'probeID', $probe_id);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleDateMinute', ts_full($cycle_date_minute));
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'testDateTime', ts_full($test_date_time));
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'testRTT', $test_rtt);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleID', $cycle_id);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'testTLD', $test_tld);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'testProtocol', $test_protocol);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'testIPVersion', $test_ipversion);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'testIPAddress', $test_ipaddress);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'testType', $test_type);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'testNSFQDN', $test_nsfqdn);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'tldType', $tld_type);
}
