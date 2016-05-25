#!/usr/bin/perl

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
	our $MYDIR2 = $0; $MYDIR2 =~ s,(.*)/.*/.*,$1,; $MYDIR2 = '..' if ($MYDIR2 eq $0);
}
use lib $MYDIR;
use lib $MYDIR2;

use warnings;
use strict;

use RSM;
use RSMSLV;
use DaWa;
use Data::Dumper;
use Time::Local;
use POSIX qw(floor);
use TLD_constants qw(:ec);
use Parallel;

use constant RDDS_SUBSERVICE => 'sub';
use constant AUDIT_FILE => '/opt/zabbix/export/last_audit.txt';
use constant AUDIT_RESOURCE_INCIDENT => 32;

use constant PROBE_STATUS_UP => 'Up';
use constant PROBE_STATUS_DOWN => 'Down';
use constant PROBE_STATUS_UNKNOWN => 'Unknown';

parse_opts('date=s', 'tld=s', 'probe=s', 'service=s', 'day=n', 'shift=n');
setopt('nolog');

set_slv_config(get_rsm_config());

db_connect();

__validate_input();

my ($d, $m, $y) = split('/', getopt('date'));

usage() unless ($d && $m && $y);

dw_set_date($y, $m, $d);

my $services;
if (opt('service'))
{
	$services->{getopt('service')} = undef;
}
else
{
	foreach my $service ('dns', 'dnssec', 'rdds', 'epp')
	{
		$services->{$service} = undef;
	}
}

my $cfg_dns_statusmaps = get_statusmaps('dns');

my $general_status_up = get_result_string($cfg_dns_statusmaps, UP);
my $general_status_down = get_result_string($cfg_dns_statusmaps, DOWN);

__get_delays($services);
__get_keys($services);
__get_valuemaps($services);
__get_max_values($services);

my $date = timelocal(0, 0, 0, $d, $m - 1, $y);

my $shift = opt('shift') ? getopt('shift') : 0;
$date += $shift;

my $day = opt('day') ? getopt('day') : 86400;

my $check_till = $date + $day - 1;
my ($from, $till) = get_real_services_period($services, $date, $check_till);

if (opt('debug'))
{
	dbg("from: ", ts_full($from));
	dbg("till: ", ts_full($till));
}

# consider only tests that started within given period
my $cfg_dns_minns;
my $cfg_dns_minonline;
my $cfg_dns_rtt_low;
foreach my $service (keys(%{$services}))
{
	dbg("$service") if (opt('debug'));

	if ($service eq 'dns' || $service eq 'dnssec')
	{
		if (!$cfg_dns_minns)
		{
			$cfg_dns_minns = get_macro_minns();
			$cfg_dns_minonline = get_macro_dns_probe_online();
			$cfg_dns_rtt_low = get_macro_dns_udp_rtt_low();
		}

		$services->{$service}->{'minns'} = $cfg_dns_minns;
		$services->{$service}->{'minonline'} = get_macro_dns_probe_online();
		$services->{$service}->{'rtt_low'} = get_macro_dns_udp_rtt_low();
	}

	if ($services->{$service}->{'from'} && $services->{$service}->{'from'} < $date)
	{
		# exclude test that starts outside our period
		$services->{$service}->{'from'} += $services->{$service}->{'delay'};
	}

	if ($services->{$service}->{'till'} && $services->{$service}->{'till'} < $check_till)
	{
		# include test that overlaps on the next period
		$services->{$service}->{'till'} += $services->{$service}->{'delay'};
	}

	if (opt('debug'))
	{
		dbg("  delay\t : ", $services->{$service}->{'delay'});
		dbg("  from\t : ", ts_full($services->{$service}->{'from'}));
		dbg("  till\t : ", ts_full($services->{$service}->{'till'}));
		dbg("  avail key\t : ", $services->{$service}->{'key_avail'});
	}
}

my $all_probes_ref = get_probes();	# we need to have the details of specified probe

if (opt('probe'))
{
	my $temp = $all_probes_ref;

	undef($all_probes_ref);

	$all_probes_ref->{getopt('probe')} = $temp->{getopt('probe')};
}

my $probe_avail_limit = get_macro_probe_avail_limit();
my $probe_times_ref = get_probe_times($from, $till, $probe_avail_limit, $all_probes_ref);

if (opt('debug'))
{
	foreach my $probe (keys(%$probe_times_ref))
	{
		my $idx = 0;

		while (defined($probe_times_ref->{$probe}->[$idx]))
		{
			my $status = ($idx % 2 == 0 ? "ONLINE" : "OFFLINE");
			dbg("$probe: $status ", ts_full($probe_times_ref->{$probe}->[$idx]), "\n");
			$idx++;
		}
	}
}

my $tlds_ref;

if (opt('tld'))
{
	my @tlds = split(',', getopt('tld'));

	foreach (@tlds)
	{
		$tld = $_;

		fail("TLD $tld does not exist.") if (tld_exists($tld) == 0);
	}

	$tlds_ref = \@tlds;
}
else
{
	$tlds_ref = get_tlds();
}

db_disconnect();

# unset TLD (for the logs)
undef($tld);

my $tld_index = 0;
my $tld_count = scalar(@$tlds_ref);

set_max_children(64);

while ($tld_index < $tld_count)
{
	my $pid = fork_without_pipe();

	if (!defined($pid))
	{
		# max children reached, make sure to handle_children()
	}
	elsif ($pid)
	{
		# parent
		$tld_index++;
	}
	else
	{
		# child
		$tld = $tlds_ref->[$tld_index];

		slv_stats_reset();

		db_disconnect();
		db_connect();

		__get_test_data($from, $till);

		db_disconnect();

		slv_exit(SUCCESS);
	}

	handle_children();
}

# wait till children finish
while (children_running() > 0)
{
	handle_children();
}

# at this point there should be no child processes so we do not care about locking

db_connect();
dw_csv_init();
dw_load_ids_from_db();

my $false_positives = __get_false_positives($from, $till);
foreach my $fp_ref (@$false_positives)
{
	dbg("writing false positive entry:");
	dbg("  eventid:", $fp_ref->{'eventid'} ? $fp_ref->{'eventid'} : "UNDEF");
	dbg("  clock:", $fp_ref->{'clock'} ? $fp_ref->{'clock'} : "UNDEF");
	dbg("  status:", $fp_ref->{'status'} ? $fp_ref->{'status'} : "UNDEF");

	dw_append_csv(DATA_FALSE_POSITIVE, [
			      $fp_ref->{'eventid'},
			      $fp_ref->{'clock'},
			      $fp_ref->{'status'},
			      ''	# reason is not implemented in front-end
		]);
}

dw_write_csv_files();
dw_write_csv_catalogs();

slv_exit(SUCCESS);

sub __validate_input
{
	my $error_found = 0;

	if (!opt('date'))
	{
		print("Error: you must specify the date using option --date\n");
		$error_found = 1;
	}

	if (opt('service'))
	{
#		if (!opt('dry-run'))
#		{
#			print("Error: option --service can only be used together with --dry-run\n");
#			$error_found = 1;
#		}

		if (getopt('service') ne 'dns' && getopt('service') ne 'dnssec' && getopt('service') ne 'rdds' && getopt('service') ne 'epp')
		{
			print("Error: \"", getopt('service'), "\" - unknown service\n");
			$error_found = 1;
		}
	}

	if (opt('probe'))
	{
#		if (!opt('dry-run'))
#		{
#			print("Error: option --probe can only be used together with --dry-run\n");
#			$error_found = 1;
#		}

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
			$error_found = 1;
			print("Error: unknown probe \"$probe\"\n");
			print("Available probes:\n");
			foreach my $name (keys(%$probes_ref))
			{
				print("  $name\n");
			}
		}
        }

	if (opt('tld'))
	{
		if (0 && !opt('dry-run'))
		{
			print("Error: option --tld can only be used together with --dry-run\n");
			$error_found = 1;
		}
	}

	if (opt('day'))
	{
		if (0 && !opt('dry-run'))
		{
			print("Error: option --day can only be used together with --dry-run\n");
			$error_found = 1;
		}

		if ((getopt('day') % 60) != 0)
		{
			print("Error: parameter of option --day must be multiple of 60\n");
		}
	}

	if (opt('shift'))
	{
		if (0 && !opt('dry-run'))
		{
			print("Error: option --shift can only be used together with --dry-run\n");
			$error_found = 1;
		}
	}

	usage() unless ($error_found == 0);
}

sub __get_delays
{
	my $cfg_dns_delay = undef;
	my $services = shift;

	foreach my $service (keys(%$services))
	{
		if ($service eq 'dns' || $service eq 'dnssec')
		{
			if (!$cfg_dns_delay)
			{
				$cfg_dns_delay = get_macro_dns_udp_delay();
			}

			$services->{$service}->{'delay'} = $cfg_dns_delay;
		}
		elsif ($service eq 'rdds')
		{
			$services->{$service}->{'delay'} = get_macro_rdds_delay();
		}
		elsif ($service eq 'epp')
		{
			$services->{$service}->{'delay'} = get_macro_epp_delay();
		}

		fail("$service delay (", $services->{$service}->{'delay'}, ") is not multiple of 60") unless ($services->{$service}->{'delay'} % 60 == 0);
	}
}

sub __get_keys
{
	my $services = shift;

	foreach my $service (keys(%$services))
	{
		if ($service eq 'dns' || $service eq 'dnssec')
		{
			$services->{$service}->{'key_status'} = 'rsm.dns.udp[{$RSM.TLD}]';	# 0 - down, 1 - up
			$services->{$service}->{'key_rtt'} = 'rsm.dns.udp.rtt[{$RSM.TLD},';
		}
		elsif ($service eq 'rdds')
		{
			$services->{$service}->{'key_status'} = 'rsm.rdds[{$RSM.TLD}';	# 0 - down, 1 - up, 2 - only 43, 3 - only 80
			$services->{$service}->{'key_43_rtt'} = 'rsm.rdds.43.rtt[{$RSM.TLD}]';
			$services->{$service}->{'key_43_ip'} = 'rsm.rdds.43.ip[{$RSM.TLD}]';
			$services->{$service}->{'key_43_upd'} = 'rsm.rdds.43.upd[{$RSM.TLD}]';
			$services->{$service}->{'key_80_rtt'} = 'rsm.rdds.80.rtt[{$RSM.TLD}]';
			$services->{$service}->{'key_80_ip'} = 'rsm.rdds.80.ip[{$RSM.TLD}]';
		}
		elsif ($service eq 'epp')
		{
			$services->{$service}->{'key_status'} = 'rsm.epp[{$RSM.TLD},';	# 0 - down, 1 - up
			$services->{$service}->{'key_ip'} = 'rsm.epp.ip[{$RSM.TLD}]';
			$services->{$service}->{'key_rtt'} = 'rsm.epp.rtt[{$RSM.TLD},';
		}

		$services->{$service}->{'key_avail'} = "rsm.slv.$service.avail";
		$services->{$service}->{'key_rollweek'} = "rsm.slv.$service.rollweek";
	}
}

sub __get_valuemaps
{
	my $services = shift;

	my $cfg_dns_valuemaps;

	foreach my $service (keys(%{$services}))
	{
		if ($service eq 'dns' || $service eq 'dnssec')
		{
			$cfg_dns_valuemaps = get_valuemaps('dns') unless ($cfg_dns_valuemaps);

			$services->{$service}->{'valuemaps'} = $cfg_dns_valuemaps;
		}
		else
		{
			$services->{$service}->{'valuemaps'} = get_valuemaps($service);
		}
	}
}

my $cfg_dns_max_value;

sub __get_max_values
{
	my $services = shift;

	foreach my $service (keys(%$services))
	{
		if ($service eq 'dns' || $service eq 'dnssec')
		{
			if (!$cfg_dns_max_value)
			{
				$cfg_dns_max_value = get_macro_dns_udp_rtt_low();
			}

			$services->{$service}->{'max_value'} = $cfg_dns_max_value;
		}
		elsif ($service eq 'rdds')
		{
			$services->{$service}->{'max_value'} = get_macro_rdds_rtt_low();
		}
		elsif ($service eq 'epp')
		{
			# TODO: max_value for EPP is based on the command
		}
	}
}

# CSV file	: nsTest
# Columns	: probeID,nsFQDNID,tldID,cycleTimestamp,status,cycleID,tldType,nsTestProtocol
#
# Note! cycleID is the concatenation of cycleDateMinute (timestamp) + serviceCategory (5) + tldID
# E. g. 1420070400-5-11
#
# How it works:
# - get list of items
# - get results:
#   "probe1" =>
#   	"ns1.foo.example" =>
#   		"192.0.1.2" =>
#   			"clock" => 1439154000,
#   			"rtt" => 120,
#   		"192.0.1.3"
#   			"clock" => 1439154000,
#   			"rtt" => 1603,
#   	"ns2.foo.example" =>
#   	...
sub __get_test_data
{
	my $from = shift;
	my $till = shift;

	my ($nsips_ref, $dns_items_ref, $rdds_dbl_items_ref, $rdds_str_items_ref, $epp_dbl_items_ref, $epp_str_items_ref,
		$probe_dns_results_ref, $result);

	foreach my $service (keys(%{$services}))
	{
		next if (tld_service_enabled($tld, $service) != SUCCESS);

		my $delay = $services->{$service}->{'delay'};
		my $service_from = $services->{$service}->{'from'};
		my $service_till = $services->{$service}->{'till'};
		my $key_avail = $services->{$service}->{'key_avail'};
		my $key_rollweek = $services->{$service}->{'key_rollweek'};

		next if (!$service_from || !$service_till);

		my $hostid = get_hostid($tld);

		my $itemid_avail = get_itemid_by_hostid($hostid, $key_avail);
		if (!$itemid_avail)
		{
			wrn("configuration error: service $service enabled but item item not found: ", rsm_slv_error());
			next;
		}

		my $itemid_rollweek = get_itemid_by_hostid($hostid, $key_rollweek);
		if (!$itemid_rollweek)
		{
			wrn("configuration error: service $service enabled but item item not found: ", rsm_slv_error());
			next;
		}

		if ($service eq 'dns' || $service eq 'dnssec')
		{
			if (!$nsips_ref)
			{
				$nsips_ref = get_nsips($tld, $services->{$service}->{'key_rtt'}, 1);	# templated
				$dns_items_ref = __get_dns_itemids($nsips_ref, $services->{$service}->{'key_rtt'}, $tld, getopt('probe'));
			}
		}
		elsif ($service eq 'rdds')
		{
			$rdds_dbl_items_ref = __get_rdds_dbl_itemids($tld, getopt('probe'));
			$rdds_str_items_ref = __get_rdds_str_itemids($tld, getopt('probe'));
		}
		elsif ($service eq 'epp')
		{
			$epp_dbl_items_ref = __get_epp_dbl_itemids($tld, getopt('probe'));
			$epp_str_items_ref = __get_epp_str_itemids($tld, getopt('probe'));
		}

		my $incidents = get_incidents2($itemid_avail, $delay, $service_from, $service_till);
		my $incidents_count = scalar(@$incidents);

		# SERVICE availability data
		my $rows_ref = db_select(
			"select value,clock".
			" from history_uint".
			" where itemid=$itemid_avail".
				" and " . sql_time_condition($service_from, $service_till).
			" order by itemid,clock");	# NB! order is important, see how the result is used below

		my $cycles;

		my $inc_idx = 0;
		my $last_avail_clock;

		foreach my $row_ref (@$rows_ref)
		{
			my $value = $row_ref->[0];
			my $clock = $row_ref->[1];

			next if ($last_avail_clock && $last_avail_clock == $clock);

			$last_avail_clock = $clock;

			dbg("$service availability at ", ts_full($clock), ": $value (inc_idx:$inc_idx)");

			# we need to count failed tests within resolved incidents
			if ($inc_idx < $incidents_count && $incidents->[$inc_idx]->{'end'})
			{
				$incidents->[$inc_idx]->{'failed_tests'} = 0 unless (defined($incidents->[$inc_idx]->{'failed_tests'}));

				while ($inc_idx < $incidents_count && $incidents->[$inc_idx]->{'end'} && $incidents->[$inc_idx]->{'end'} < $clock)
				{
					$inc_idx++;
				}

				if ($value == DOWN && $inc_idx < $incidents_count && $incidents->[$inc_idx]->{'end'} && $clock >= $incidents->[$inc_idx]->{'start'} && $incidents->[$inc_idx]->{'end'} >= $clock)
				{
					$incidents->[$inc_idx]->{'failed_tests'}++;
				}
			}

			wrn("unknown availability result: $value (expected ", DOWN, " (Down), ", UP, " (Up) or ", UP_INCONCLUSIVE, " (Up (inconclusive))")
				if ($value != UP && $value != DOWN && $value != UP_INCONCLUSIVE);

			# We have the test resulting value (Up or Down) at "clock". Now we need to select the
			# time bounds (start/end) of all data points from all proxies.
			#
			#   +........................period (service delay)...........................+
			#   |                                                                         |
			# start                                 clock                                end
			#   |.....................................|...................................|
			#   0 seconds <--zero or more minutes--> 30                                  59
			#

			my $cycleclock = cycle_start($clock, $delay);

			$cycles->{$cycleclock}->{'status'} = get_result_string($cfg_dns_statusmaps, $value);
		}

		# Rolling week data (is synced with availability data from above)
		$rows_ref = db_select(
			"select value,clock".
			" from history".
			" where itemid=$itemid_rollweek".
				" and " . sql_time_condition($service_from, $service_till).
			" order by itemid,clock");	# NB! order is important, see how the result is used below

		foreach my $row_ref (@$rows_ref)
		{
			my $value = $row_ref->[0];
			my $clock = $row_ref->[1];

			dbg("$service rolling week at ", ts_full($clock), ": $value");

			my $cycleclock = cycle_start($clock, $delay);

			$cycles->{$cycleclock}->{'rollweek'} = $value;
		}

		my $cycles_count = scalar(keys(%{$cycles}));

		if ($cycles_count == 0)
		{
			wrn("$service: no results");
			last;
		}

		my $tests_ref;

		if ($service eq 'dns')
		{
			$tests_ref = get_dns_test_values($dns_items_ref, $service_from, $service_till,
				$services->{$service}->{'valuemaps'}, $delay, $service);
		}
		elsif ($service eq 'rdds')
		{
			$tests_ref = get_rdds_test_values($rdds_dbl_items_ref, $rdds_str_items_ref,
				$service_from, $service_till, $services->{$service}->{'valuemaps'}, $delay);
		}
		elsif ($service eq 'epp')
		{
			wrn("EPP: not implemented yet");
			next;
		}

		# add tests to appropriate cycles
		foreach my $cycleclock (keys(%$tests_ref))
		{
			if (!$cycles->{$cycleclock})
			{
				no_cycle_result($service, $key_avail, $cycleclock);
				next;
			}

			foreach my $interface (keys(%{$tests_ref->{$cycleclock}}))
			{
				foreach my $probe (keys(%{$tests_ref->{$cycleclock}->{$interface}}))
				{
					# the status is set later
					$cycles->{$cycleclock}->{'interfaces'}->{$interface}->{'probes'}->{$probe}->{'status'} = undef;

					if (probe_offline_at($probe_times_ref, $probe, $cycleclock) != 0)
					{
						$cycles->{$cycleclock}->{'interfaces'}->{$interface}->{'probes'}->{$probe}->{'status'} = PROBE_OFFLINE_STR;
					}

					$cycles->{$cycleclock}->{'interfaces'}->{$interface}->{'probes'}->{$probe}->{'targets'} = $tests_ref->{$cycleclock}->{$interface}->{$probe};
				}
			}
		}

		my $probe_results_ref;

		# add availability results from probes, working services: (dns: number of NS, rdds: 43, 80)
		if ($service eq 'dns' || $service eq 'dnssec')
		{
			if (!$probe_dns_results_ref)
			{
				my $itemids_ref = get_service_status_itemids($tld, $services->{$service}->{'key_status'});
				my $probe_results_ref = get_probe_results($itemids_ref, $service_from, $service_till);
			}

			$probe_results_ref = $probe_dns_results_ref;
		}
		else
		{
			my $itemids_ref = get_service_status_itemids($tld, $services->{$service}->{'key_status'});
			$probe_results_ref = get_probe_results($itemids_ref, $service_from, $service_till);
		}

		foreach my $cycleclock (keys(%$cycles))
		{
			# set status on particular probe
			foreach my $interface (keys(%{$cycles->{$cycleclock}->{'interfaces'}}))
			{
				foreach my $probe (keys(%{$cycles->{$cycleclock}->{'interfaces'}->{$interface}->{'probes'}}))
				{
					foreach my $probe_result_ref (@{$probe_results_ref->{$probe}})
					{
						if (!defined($cycles->{$cycleclock}->{'interfaces'}->{$interface}->{'probes'}->{$probe}->{'status'}))
						{
							$cycles->{$cycleclock}->{'interfaces'}->{$interface}->{'probes'}->{$probe}->{'status'} =
								__interface_status($interface, $probe_result_ref->{'value'}, $services->{$service});
						}
					}
				}
			}
		}

		$result->{$tld}->{$service}->{'cycles'} = $cycles;
		$result->{$tld}->{$service}->{'incidents'} = $incidents;
	}

	# push data to CSV files
	foreach (sort(keys(%$result)))
	{
		$tld = $_;	# set to global variable

		slv_lock();
		dw_csv_init();
		dw_load_ids_from_db();

		my $ns_service_category_id = dw_get_id(ID_SERVICE_CATEGORY, 'ns');
		my $dns_service_category_id = dw_get_id(ID_SERVICE_CATEGORY, 'dns');
		my $dnssec_service_category_id = dw_get_id(ID_SERVICE_CATEGORY, 'dnssec');
		my $rdds_service_category_id = dw_get_id(ID_SERVICE_CATEGORY, 'rdds');
		my $epp_service_category_id = dw_get_id(ID_SERVICE_CATEGORY, 'epp');
		my $udp_protocol_id = dw_get_id(ID_TRANSPORT_PROTOCOL, 'udp');
		my $tcp_protocol_id = dw_get_id(ID_TRANSPORT_PROTOCOL, 'tcp');

		my $tld_id = dw_get_id(ID_TLD, $tld);
		my $tld_type_id = dw_get_id(ID_TLD_TYPE, __get_tld_type($tld));

		foreach my $service (sort(keys(%{$result->{$tld}})))
		{
			my $service_ref = $services->{$service};

			my $service_category_id;
			my $protocol_id;

			if ($service eq 'dns')
			{
				$service_category_id = $dns_service_category_id;
				$protocol_id = $udp_protocol_id;
			}
			elsif ($service eq 'dnssec')
			{
				$service_category_id = $dnssec_service_category_id;
				$protocol_id = $udp_protocol_id;
			}
			elsif ($service eq 'rdds')
			{
				$service_category_id = $rdds_service_category_id;
				$protocol_id = $tcp_protocol_id;
			}
			elsif ($service eq 'epp')
			{
				$service_category_id = $epp_service_category_id;
				$protocol_id = $tcp_protocol_id;
			}

			my $incidents = $result->{$tld}->{$service}->{'incidents'};
			my $incidents_count = scalar(@$incidents);
			my $inc_idx = 0;

			# test results
			foreach my $cycleclock (keys(%{$result->{$tld}->{$service}->{'cycles'}}))
			{
				my $cycle_ref = $result->{$tld}->{$service}->{'cycles'}->{$cycleclock};

				my %nscycle;	# for Name Server cycle

				my $eventid = '';

				if ($inc_idx < $incidents_count)
				{
					while ($inc_idx < $incidents_count && $incidents->[$inc_idx]->{'end'} && $incidents->[$inc_idx]->{'end'} < $cycleclock)
					{
						$inc_idx++;
					}

					if ($inc_idx < $incidents_count && (!$incidents->[$inc_idx]->{'end'} || $cycleclock >= $incidents->[$inc_idx]->{'start'} && $incidents->[$inc_idx]->{'end'} >= $cycleclock))
					{
						$eventid = $incidents->[$inc_idx]->{'eventid'};
					}
				}

				# SERVICE cycle
				dw_append_csv(DATA_CYCLE, [
						      dw_get_cycle_id($cycleclock, $service_category_id, $tld_id),
						      $cycleclock,
						      $cycle_ref->{'rollweek'},
						      dw_get_id(ID_STATUS_MAP, $cycle_ref->{'status'}),
						      $eventid,
						      $tld_id,
						      $service_category_id,
						      '',
						      '',
						      '',
						      $tld_type_id,
						      $protocol_id
					]);

				foreach my $interface (keys(%{$cycle_ref->{'interfaces'}}))
				{
					foreach my $probe (keys(%{$cycle_ref->{'interfaces'}->{$interface}->{'probes'}}))
					{
						my $probe_id = dw_get_id(ID_PROBE, $probe);

						foreach my $target (keys(%{$cycle_ref->{'interfaces'}->{$interface}->{'probes'}->{$probe}->{'targets'}}))
						{
							my $target_status = $general_status_up;
							my $target_id = '';

							if ($interface eq 'DNS')
							{
								$target_id = dw_get_id(ID_NS_NAME, $target);
							}

							foreach my $metric_ref (@{$cycle_ref->{'interfaces'}->{$interface}->{'probes'}->{$probe}->{'targets'}->{$target}})
							{
								my $test_status;

								if (__check_test($interface, $metric_ref->{JSON_TAG_RTT()}, $metric_ref->{JSON_TAG_DESCRIPTION()}, $service_ref->{'max_value'}) == SUCCESS)
								{
									$test_status = $general_status_up;
								}
								else
								{
									$test_status = $general_status_down;
								}

								if ($target_status eq $general_status_up)
								{
									if ($test_status eq $general_status_down)
									{
										$target_status = $general_status_down;
									}
								}

								my $testclock = $metric_ref->{JSON_TAG_CLOCK()};

								my ($ip, $ip_id, $ip_version_id, $rtt);

								if ($metric_ref->{JSON_TAG_TARGET_IP()})
								{
									$ip = $metric_ref->{JSON_TAG_TARGET_IP()};
									$ip_id = dw_get_id(ID_NS_IP, $ip);
									$ip_version_id = dw_get_id(ID_IP_VERSION, __ip_version($ip));
								}
								else
								{
									$ip = '';
									$ip_id = '';
									$ip_version_id = '';
								}

								if ($metric_ref->{JSON_TAG_RTT()})
								{
									$rtt = $metric_ref->{JSON_TAG_RTT()};
								}
								else
								{
									if ($metric_ref->{JSON_TAG_DESCRIPTION()})
									{
										my @a = split(',', $metric_ref->{JSON_TAG_DESCRIPTION()});
										$rtt = $a[0];
									}
									else
									{
										$rtt = '';
									}
								}

								# TEST
								__add_csv_test(
									dw_get_cycle_id($cycleclock, $service_category_id, $tld_id, $target_id, $ip_id),
									$probe_id,
									$cycleclock,
									$testclock,
									$rtt,
									$service_category_id,
									$tld_id,
									$protocol_id,
									$ip_version_id,
									$ip_id,
									dw_get_id(ID_TEST_TYPE, lc($interface)),
									$target_id,
									$tld_type_id
									);

								if ($ip)
								{
									if (!defined($nscycle{$target}) || !defined($nscycle{$target}{$ip}))
									{
										$nscycle{$target}{$ip}{'total'} = 0;
										$nscycle{$target}{$ip}{'positive'} = 0;
									}

									$nscycle{$target}{$ip}{'total'}++;
									$nscycle{$target}{$ip}{'positive'}++ if ($test_status eq $general_status_up);
								}
							}

							if ($interface eq 'DNS')
							{
								# Name Server (target) test
								dw_append_csv(DATA_NSTEST, [
										      $probe_id,
										      $target_id,
										      $tld_id,
										      $cycleclock,
										      dw_get_id(ID_STATUS_MAP, $target_status),
										      dw_get_cycle_id($cycleclock, $ns_service_category_id, $tld_id),
										      $tld_type_id,
										      $protocol_id
									]);
							}
						}
					}


					if ($interface eq 'DNS')
					{
						foreach my $ns (keys(%nscycle))
						{
							foreach my $ip (keys(%{$nscycle{$ns}}))
							{
								dbg("NS $ns,$ip : positive ", $nscycle{$ns}{$ip}{'positive'}, "/", $nscycle{$ns}{$ip}{'total'});

								my $nscyclestatus;

								if ($nscycle{$ns}{$ip}{'total'} < $services->{$service}->{'minonline'})
								{
									$nscyclestatus = $general_status_up;
								}
								else
								{
									my $perc = $nscycle{$ns}{$ip}{'positive'} * 100 / $nscycle{$ns}{$ip}{'total'};
									$nscyclestatus = ($perc > SLV_UNAVAILABILITY_LIMIT ? $general_status_up : $general_status_down);
								}

								dbg("get ip version, csv:ns_avail service:$service, ip:", (defined($ip) ? $ip : "UNDEF"));

								my $ns_id = dw_get_id(ID_NS_NAME, $ns);
								my $ip_id = dw_get_id(ID_NS_IP, $ip);

								# Name Server availability cycle
								dw_append_csv(DATA_CYCLE, [
										      dw_get_cycle_id($cycleclock, $ns_service_category_id, $tld_id, $ns_id, $ip_id),
										      $cycleclock,
										      0,	# TODO: emergency threshold not yet supported for NS Availability
										      dw_get_id(ID_STATUS_MAP, $nscyclestatus),
										      '',	# TODO: incident ID not yet supported for NS Availability
										      $tld_id,
										      $ns_service_category_id,
										      $ns_id,
										      $ip_id,
										      dw_get_id(ID_IP_VERSION, __ip_version($ip)),
										      $tld_type_id,
										      $protocol_id
									]);
							}
						}
					}
				}
			}

			# incidents
			foreach (@$incidents)
			{
				my $eventid = $_->{'eventid'};
				my $event_start = $_->{'start'};
				my $event_end = $_->{'end'};
				my $failed_tests = $_->{'failed_tests'};
				my $false_positive = $_->{'false_positive'};

				dbg("incident id:$eventid start:", ts_full($event_start), " end:", ts_full($event_end), " fp:$false_positive failed_tests:", (defined($failed_tests) ? $failed_tests : "(null)")) if (opt('debug'));

				# write event that resolves incident
				if ($event_end)
				{
					dw_append_csv(DATA_INCIDENT_END, [
							      $eventid,
							      $event_end,
							      $failed_tests
						]);
				}

				# report only incidents within given period
				if ($event_start > $from)
				{
					dw_append_csv(DATA_INCIDENT, [
							      $eventid,
							      $event_start,
							      $tld_id,
							      $service_category_id,
							      $tld_type_id
						]);
				}
			}
		}

		slv_unlock();
	}

	my $real_tld = $tld;
	$tld = get_readable_tld($real_tld);
	dw_write_csv_files();
	$tld = $real_tld;
}

sub __add_csv_test
{
	my $cycle_id = shift;
	my $probe_id = shift;
	my $cycleclock = shift;
	my $testclock = shift;
	my $rtt = shift;
	my $service_category_id = shift;
	my $tld_id = shift;
	my $protocol_id = shift;
	my $ip_version_id = shift;
	my $ip_id = shift;
	my $test_type_id = shift;
	my $ns_id = shift;
	my $tld_type_id = shift;

	dw_append_csv(DATA_TEST, [
			      $probe_id,
			      $cycleclock,
			      $testclock,
			      __format_rtt($rtt),
			      $cycle_id,
			      $tld_id,
			      $protocol_id,
			      $ip_version_id,
			      $ip_id,
			      $test_type_id,
			      $ns_id,
			      $tld_type_id
		]);
}

sub __ip_version
{
	my $addr = shift;

	return 'IPv6' if ($addr =~ /:/);

	return 'IPv4';
}

sub __get_tld_type
{
	my $tld = shift;

	my $rows_ref = db_select(
		"select g.name".
		" from hosts_groups hg,groups g,hosts h".
		" where hg.groupid=g.groupid".
			" and hg.hostid=h.hostid".
			" and g.name like '%TLD'".
			" and h.host='$tld'");

	if (scalar(@$rows_ref) != 1)
	{
		fail("cannot get type of TLD $tld");
	}

	return $rows_ref->[0]->[0];
}

# return itemids grouped by Probes:
#
# {
#    'Amsterdam' => {
#         'itemid1' => 'ns2,2620:0:2d0:270::1:201',
#         'itemid2' => 'ns1,192.0.34.201'
#    },
#    'London' => {
#         'itemid3' => 'ns2,2620:0:2d0:270::1:201',
#         'itemid4' => 'ns1,192.0.34.201'
#    }
# }
sub __get_dns_itemids
{
	my $nsips_ref = shift; # array reference of NS,IP pairs
	my $key = shift;
	my $tld = shift;
	my $probe = shift;

	my @keys;
	push(@keys, "'" . $key . $_ . "]'") foreach (@$nsips_ref);

	my $keys_str = join(',', @keys);

	my $host_value = ($probe ? "$tld $probe" : "$tld %");

	my $rows_ref = db_select(
		"select h.host,i.itemid,i.key_".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and h.host like '$host_value'".
			" and i.templateid is not null".
			" and i.key_ in ($keys_str)");

	my %result;

	my $tld_length = length($tld) + 1; # white space
	foreach my $row_ref (@$rows_ref)
	{
		my $host = $row_ref->[0];
		my $itemid = $row_ref->[1];
		my $key = $row_ref->[2];

		# remove TLD from host name to get just the Probe name
		my $_probe = ($probe ? $probe : substr($host, $tld_length));

		$result{$_probe}->{$itemid} = get_nsip_from_key($key);
	}

	fail("cannot find items ($keys_str) at host ($tld *)") if (scalar(keys(%result)) == 0);

	return \%result;
}

# $keys_str - list of complete keys
sub __get_itemids_by_complete_key
{
	my $tld = shift;
	my $probe = shift;

	my $keys_str = "'" . join("','", @_) . "'";

	my $host_value = ($probe ? "$tld $probe" : "$tld %");

	my $rows_ref = db_select(
		"select h.host,i.itemid,i.key_".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and h.host like '$host_value'".
			" and i.key_ in ($keys_str)".
			" and i.templateid is not null");

	my %result;

	my $tld_length = length($tld) + 1; # white space
	foreach my $row_ref (@$rows_ref)
	{
		my $host = $row_ref->[0];
		my $itemid = $row_ref->[1];
		my $key = $row_ref->[2];

		# remove TLD from host name to get just the Probe name
		my $_probe = ($probe ? $probe : substr($host, $tld_length));

		$result{$_probe}->{$itemid} = $key;
	}

	fail("cannot find items ($keys_str) at host ($tld *)") if (scalar(keys(%result)) == 0);

	return \%result;
}

# return itemids of dbl items grouped by Probes:
#
# {
#    'Amsterdam' => {
#         'itemid1' => 'rsm.rdds.43.rtt...',
#         'itemid2' => 'rsm.rdds.43.upd...',
#         'itemid3' => 'rsm.rdds.80.rtt...'
#    },
#    'London' => {
#         'itemid4' => 'rsm.rdds.43.rtt...',
#         'itemid5' => 'rsm.rdds.43.upd...',
#         'itemid6' => 'rsm.rdds.80.rtt...'
#    }
# }
sub __get_rdds_dbl_itemids
{
	my $tld = shift;
	my $probe = shift;

	return __get_itemids_by_complete_key($tld, $probe, $services->{'rdds'}{'key_43_rtt'}, $services->{'rdds'}{'key_80_rtt'}, $services->{'rdds'}{'key_43_upd'});
}

# return itemids of string items grouped by Probes:
#
# {
#    'Amsterdam' => {
#         'itemid1' => 'rsm.rdds.43.ip...',
#         'itemid2' => 'rsm.rdds.80.ip...'
#    },
#    'London' => {
#         'itemid3' => 'rsm.rdds.43.ip...',
#         'itemid4' => 'rsm.rdds.80.ip...'
#    }
# }
sub __get_rdds_str_itemids
{
	my $tld = shift;
	my $probe = shift;

	return __get_itemids_by_complete_key($tld, $probe, $services->{'rdds'}{'key_43_ip'}, $services->{'rdds'}{'key_80_ip'});
}

# returns hash reference of Probe=>itemid of specified key
#
# {
#    'Amsterdam' => 'itemid1',
#    'London' => 'itemid2',
#    ...
# }
sub __get_status_itemids
{
	my $tld = shift;
	my $key = shift;

	my $key_condition = (substr($key, -1) eq ']' ? "i.key_='$key'" : "i.key_ like '$key%'");

	my $sql =
		"select h.host,i.itemid".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and i.templateid is not null".
			" and $key_condition".
			" and h.host like '$tld %'".
		" group by h.host,i.itemid";

	my $rows_ref = db_select($sql);

	fail("no items matching '$key' found at host '$tld %'") if (scalar(@$rows_ref) == 0);

	my %result;

	my $tld_length = length($tld) + 1; # white space
	foreach my $row_ref (@$rows_ref)
	{
		my $host = $row_ref->[0];
		my $itemid = $row_ref->[1];

		# remove TLD from host name to get just the Probe name
		my $probe = substr($host, $tld_length);

		$result{$probe} = $itemid;
	}

	return \%result;
}

#
# {
#     'Probe1' =>
#     [
#         {
#             'clock' => 1234234234,
#             'value' => 'Up'
#         },
#         {
#             'clock' => 1234234294,
#             'value' => 'Up'
#         }
#     ],
#     'Probe2' =>
#     [
#         {
#             'clock' => 1234234234,
#             'value' => 'Down'
#         },
#         {
#             'clock' => 1234234294,
#             'value' => 'Up'
#         }
#     ]
# }
#
sub __get_probe_statuses
{
	my $itemids_ref = shift;
	my $from = shift;
	my $till = shift;

	my %result;

	# generate list if itemids
	my @itemids;
	foreach my $probe (keys(%$itemids_ref))
	{
		push(@itemids, $itemids_ref->{$probe});
	}

	if (scalar(@itemids) != 0)
	{
		my $rows_ref = db_select_binds(
			"select itemid,value,clock" .
			" from history_uint" .
			" where itemid=?" .
				" and " . sql_time_condition($from, $till),
			\@itemids);

		# NB! It's important to order by clock here, see how this result is used.
		foreach my $row_ref (sort { $a->[2] <=> $b->[2] } @$rows_ref)
		{
			my $itemid = $row_ref->[0];
			my $value = $row_ref->[1];
			my $clock = $row_ref->[2];

			my $probe;
			foreach my $pr (keys(%$itemids_ref))
			{
				my $i = $itemids_ref->{$pr};

				if ($i == $itemid)
				{
					$probe = $pr;

					last;
				}
			}

			fail("internal error: Probe of item (itemid:$itemid) not found") unless (defined($probe));

			push(@{$result{$probe}}, {'value' => $value, 'clock' => $clock});
		}
	}

	return \%result;
}

sub __check_dns_udp_rtt
{
	my $value = shift;
	my $max_rtt = shift;

	return (is_service_error($value) == SUCCESS or $value > $max_rtt) ? E_FAIL : SUCCESS;
}

sub __get_false_positives
{
	my $from = shift;
	my $till = shift;

	my @result;

	# check for possible false_positive changes made in front-end
	my $rows_ref = db_select(
		"select details,clock".
		" from auditlog".
		" where resourcetype=" . AUDIT_RESOURCE_INCIDENT.
			" and clock between $from and $till".
		" order by clock");

	foreach my $row_ref (@$rows_ref)
	{
		my $details = $row_ref->[0];
		my $clock = $row_ref->[1];

		my $eventid = $details;
		$eventid =~ s/^([0-9]+): .*/$1/;

		my $status = 'activated';
		if ($details =~ m/unmark/i)
		{
			$status = 'deactivated';
		}

		push(@result, {'clock' => $clock, 'eventid' => $eventid, 'status' => $status});
	}

	return \@result;
}

sub __format_rtt
{
	my $rtt = shift;

	return "UNDEF" unless (defined($rtt));		# it should never be undefined

	return $rtt unless ($rtt);			# allow empty string (in case of error)

	return int($rtt);
}

sub __check_test
{
	my $interface = shift;
	my $value = shift;
	my $description = shift;
	my $max_value = shift;

	if ($interface eq JSON_INTERFACE_DNSSEC)
	{
		if (defined($description))
		{
			my $error_code_len = length(ZBX_EC_DNS_NS_ERRSIG);
			my $error_code = substr($description, 0, $error_code_len);

			if ($error_code eq ZBX_EC_DNS_NS_ERRSIG)
			{
				return E_FAIL;
			}
		}

		return SUCCESS;
	}

	return E_FAIL unless ($value);

	return (is_service_error($value) == SUCCESS or $value > $max_value) ? E_FAIL : SUCCESS;
}

sub __interface_status
{
	my $interface = shift;
	my $value = shift;
	my $service_ref = shift;

	my $status;

	if ($interface eq JSON_INTERFACE_DNS)
	{
		$status = ($value >= $service_ref->{'minns'} ? UP : DOWN);
	}
	elsif ($interface eq JSON_INTERFACE_DNSSEC)
	{
		# TODO: dnssec status on a particular probe is not supported currently,
		# make this calculation in function __create_cycle_hash() for now.
	}
	elsif ($interface eq JSON_INTERFACE_RDDS43 || $interface eq JSON_INTERFACE_RDDS80)
	{
		my $service_only = ($interface eq JSON_INTERFACE_RDDS43 ? 2 : 3);	# 0 - down, 1 - up, 2 - only 43, 3 - only 80

		$status = (($value == 1 || $value == $service_only) ? UP : DOWN);
	}
	else
	{
		fail("$interface: unsupported interface");
	}

	return $status;
}

__END__

=head1 NAME

export.pl - export data from Zabbix database in CSV format

=head1 SYNOPSIS

export.pl --date <dd/mm/yyyy> [--warnslow <seconds>] [--dry-run] [--debug] [--probe <name>] [--tld <name>] [--service <name>] [--day <seconds>] [--shift <seconds>] [--help]

=head1 OPTIONS

=over 8

=item B<--date> dd/mm/yyyy

Process data of the specified day. E. g. 01/10/2015 .

=item B<--dry-run>

Print data to the screen, do not write anything to the filesystem.

=item B<--warnslow> seconds

Issue a warning in case an SQL query takes more than specified number of seconds. A floating-point number
is supported as seconds (i. e. 0.5, 1, 1.5 are valid).

=item B<--debug>

Run the script in debug mode. This means printing more information.

=item B<--probe> name

Specify probe name. All other probes will be ignored.

Implies option --dry-run.

=item B<--tld> name

Specify TLD. All other TLDs will be ignored.

Implies option --dry-run.

=item B<--service> name

Specify service. All other services will be ignored. Known services are: dns, dnssec, rdds, epp.

Implies option --dry-run.

=item B<--day> seconds

Specify length of the day in seconds. By default 1 day equals 86400 seconds.

Implies option --dry-run.

=item B<--shift> seconds

Move forward specified number of seconds from the date specified with --date.

Implies option --dry-run.

=item B<--help>

Print a brief help message and exit.

=back

=head1 DESCRIPTION

B<This program> will collect monitoring data from Zabbix database and save it in CSV format in different files.

=head1 EXAMPLES

./export.pl --date 01/10/2015

This will process monitoring data of the 1st of October 2015.

=cut
