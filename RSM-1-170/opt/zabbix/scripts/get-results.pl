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

parse_opts('tld=s', 'from=n', 'till=n', 'service=s');

# do not write any logs
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

my ($key, $cfg_max_value, $service_type, $delay);

my ($from, $till);

if (opt('from'))
{
	$from = truncate_from(getopt('from'));
}
if (opt('till'))
{
	$till = truncate_till(getopt('till'));
}

if (getopt('service') eq 'tcp-dns-rtt')
{
	$key = 'rsm.dns.tcp.rtt[{$RSM.TLD},';
	$cfg_max_value = get_macro_dns_tcp_rtt_low();
	$delay = get_macro_dns_tcp_delay();
	$service_type = 'DNS';
}
elsif (getopt('service') eq 'udp-dns-rtt')
{
	$key = 'rsm.dns.udp.rtt[{$RSM.TLD},';
	$cfg_max_value = get_macro_dns_udp_rtt_low();
	$delay = get_macro_dns_udp_delay();
	$service_type = 'DNS';
}
elsif (getopt('service') eq 'dns-upd')
{
	$key = 'rsm.dns.udp.upd[{$RSM.TLD},';
	$cfg_max_value = get_macro_dns_update_time();
	$delay = get_macro_dns_udp_delay();
	$service_type = 'EPP';
}
elsif (getopt('service') eq 'rdds43-rtt')
{
	$key = 'rsm.rdds.43.rtt[{$RSM.TLD}]';
	$cfg_max_value = get_macro_rdds_rtt_low();
	$delay = get_macro_rdds_delay();
	$service_type = 'RDDS';
}
elsif (getopt('service') eq 'rdds80-rtt')
{
	$key = 'rsm.rdds.80.rtt[{$RSM.TLD}]';
	$cfg_max_value = get_macro_rdds_rtt_low();
	$delay = get_macro_rdds_delay();
	$service_type = 'RDDS';
}
elsif (getopt('service') eq 'rdds-upd')
{
	$key = 'rsm.rdds.43.upd[{$RSM.TLD}]';
	$cfg_max_value = get_macro_rdds_rtt_low();
	$delay = get_macro_rdds_delay();
	$service_type = 'EPP';
}
elsif (getopt('service') eq 'epp-login-rtt')
{
	my $command = 'login';
	$key = 'rsm.epp.rtt[{$RSM.TLD},' . $command . ']';
	$cfg_max_value = get_macro_epp_rtt_low($command);
	$delay = get_macro_epp_delay();
	$service_type = 'EPP';
}
elsif (getopt('service') eq 'epp-info-rtt')
{
	my $command = 'info';
	$key = 'rsm.epp.rtt[{$RSM.TLD},' . $command . ']';
	$cfg_max_value = get_macro_epp_rtt_low($command);
	$delay = get_macro_epp_delay();
	$service_type = 'EPP';
}
elsif (getopt('service') eq 'epp-update-rtt')
{
	my $command = 'update';
	$key = 'rsm.epp.rtt[{$RSM.TLD},' . $command . ']';
	$cfg_max_value = get_macro_epp_rtt_low($command);
	$delay = get_macro_epp_delay();
	$service_type = 'EPP';
}
else
{
	print("Invalid service specified \"", getopt('service'), "\"\n");
	usage(2);
}

($from, $till) = get_default_period(time() - $delay - AVAIL_SHIFT_BACK, $delay, getopt('from'), getopt('till'));

info('selected period: ', selected_period($from, $till));

my $probes_ref = get_probes($service_type);
my $probe_times_ref = get_probe_times($from, $till, $probes_ref);
my $tlds_ref = opt('tld') ? [ getopt('tld') ] : get_tlds($service_type);

foreach (@$tlds_ref)
{
	$tld = $_;

	my $items_ref;

	if ("," eq substr($key, -1))
	{
		my $nsips_ref = get_templated_nsips($tld, $key);
		$items_ref = __get_all_ns_items($nsips_ref, $key, $tld);
	}
	else
	{
		$items_ref = get_all_items($key, $tld);
	}

	my $result = get_results($tld, $probe_times_ref, $items_ref, \&__check_test);

	foreach my $nsip (keys(%$result))
	{
		my $total = $result->{$nsip}->{'total'};
		my $successful = $result->{$nsip}->{'successful'};
		my $nsip_label = '';

		$nsip_label = "$nsip: " unless ($nsip eq '');

		if ($total == 0)
		{
			info($nsip_label, "no results found in the database from ", ts_str($from), " ($from) till ", ts_str($till), " ($till)");
			next;
		}

		info($nsip_label, "$successful/$total successful results from ", ts_str($from), " ($from) till ", ts_str($till), " ($till)");
	}
}

# unset TLD (for the logs)
$tld = undef;

slv_exit(SUCCESS);

sub __check_test
{
	my $value = shift;

	return (is_service_error($value) == SUCCESS or $value > $cfg_max_value) ? E_FAIL : SUCCESS;
}

sub __get_all_ns_items
{
	my $nss_ref = shift; # array reference of name servers ("name,IP")
	my $cfg_key_in = shift;
	my $tld = shift;

	my @keys;
	push(@keys, "'" . $cfg_key_in . $_ . "]'") foreach (@$nss_ref);

	my $keys_str = join(',', @keys);

	my $rows_ref = db_select(
		"select h.hostid,i.itemid,i.key_,h.host ".
		"from items i,hosts h ".
		"where i.hostid=h.hostid".
			" and h.host like '$tld %'".
			" and i.templateid is not null".
			" and i.key_ in ($keys_str)");

	my %all_ns_items;
	foreach my $row_ref (@$rows_ref)
	{
		$all_ns_items{$row_ref->[0]}{$row_ref->[1]} = get_nsip_from_key($row_ref->[2]);
	}

	fail("cannot find items ($keys_str) at host ($tld *)") if (scalar(keys(%all_ns_items)) == 0);

	return \%all_ns_items;
}

__END__

=head1 NAME

get-results.pl - get successful/total test results of the service for given period of time

=head1 SYNOPSIS

get-results.pl --service <tcp-dns-rtt|udp-dns-rtt|dns-upd|rdds43-rtt|rdds80-rtt|rdds-upd|epp-login-rtt|epp-info-rtt|epp-update-rtt> [--tld tld] [--from timestamp] [--till timestamp] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--service> name

Specify the name of the service. Supported services: tcp-dns-rtt, udp-dns-rtt, dns-upd, rdds43-rtt, rdds80-rtt, rdds-upd, epp-login-rtt, epp-info-rtt, epp-update-rtt.

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
