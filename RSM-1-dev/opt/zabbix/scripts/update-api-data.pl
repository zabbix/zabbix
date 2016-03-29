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
use ApiHelper;
use TLD_constants qw(:ec);
use Data::Dumper;

use constant AUDIT_RESOURCE_INCIDENT => 32;

parse_opts('tld=s', 'service=s', 'period=n', 'from=n', 'continue!', 'ignore-file=s', 'probe=s', 'limit=n', 'now=n', 'base=s');

# do not write any logs
setopt('nolog');

exit_if_running();

if (opt('debug'))
{
	dbg("command-line parameters:");
	dbg("$_ => ", getopt($_)) foreach (optkeys());
}

set_slv_config(get_rsm_config());

db_connect();

__validate_input();

if (opt('base'))
{
	if (ah_set_base_dir(getopt('base')) != AH_SUCCESS)
	{
		fail("cannot set base directory: ", ah_get_error());
	}
}

my $opt_from = getopt('from');

if (defined($opt_from))
{
	$opt_from = truncate_from($opt_from);	# use the whole minute
	dbg("option \"from\" truncated to the start of a minute: $opt_from") if ($opt_from != getopt('from'));
}

my $services;
if (opt('service'))
{
	$services->{lc(getopt('service'))} = undef;
}
else
{
	foreach my $service ('dns', 'dnssec', 'rdds', 'epp')
	{
		$services->{$service} = undef;
	}
}

my %ignore_hash;

if (opt('ignore-file'))
{
	my $ignore_file = getopt('ignore-file');

	my $handle;
	fail("cannot open ignore file \"$ignore_file\": $!") unless open($handle, '<', $ignore_file);

	chomp(my @lines = <$handle>);

	close($handle);

	%ignore_hash = map { $_ => 1 } @lines;
}

my $cfg_dns_delay = undef;
my $cfg_dns_minns;
my $cfg_dns_valuemaps;
my $cfg_dns_max_value;
my $cfg_dns_minonline;

my $cfg_dns_statusmaps = get_statusmaps('dns');

foreach my $service (keys(%{$services}))
{
	if ($service eq 'dns' || $service eq 'dnssec')
	{
		if (!$cfg_dns_delay)
		{
			$cfg_dns_delay = get_macro_dns_udp_delay();
			$cfg_dns_minns = get_macro_minns();
			$cfg_dns_valuemaps = get_valuemaps('dns');
			$cfg_dns_max_value = get_macro_dns_udp_rtt_low();
			$cfg_dns_minonline = get_macro_dns_probe_online();
		}

		$services->{$service}->{'delay'} = $cfg_dns_delay;
		$services->{$service}->{'minns'} = $cfg_dns_minns;
		$services->{$service}->{'valuemaps'} = $cfg_dns_valuemaps;
		$services->{$service}->{'max_value'} = $cfg_dns_max_value;
		$services->{$service}->{'minonline'} = $cfg_dns_minonline;
		$services->{$service}->{'key_status'} = 'rsm.dns.udp[{$RSM.TLD}]';	# 0 - down, 1 - up
		$services->{$service}->{'key_rtt'} = 'rsm.dns.udp.rtt[{$RSM.TLD},';
	}
	elsif ($service eq 'rdds')
	{
		$services->{$service}->{'delay'} = get_macro_rdds_delay();
		$services->{$service}->{'valuemaps'} = get_valuemaps($service);
		$services->{$service}->{'max_value'} = get_macro_rdds_rtt_low();
		$services->{$service}->{'minonline'} = get_macro_rdds_probe_online();
		$services->{$service}->{'key_status'} = 'rsm.rdds[{$RSM.TLD}';	# 0 - down, 1 - up, 2 - only 43, 3 - only 80
		$services->{$service}->{'key_43_rtt'} = 'rsm.rdds.43.rtt[{$RSM.TLD}]';
		$services->{$service}->{'key_43_ip'} = 'rsm.rdds.43.ip[{$RSM.TLD}]';
		$services->{$service}->{'key_43_upd'} = 'rsm.rdds.43.upd[{$RSM.TLD}]';
		$services->{$service}->{'key_80_rtt'} = 'rsm.rdds.80.rtt[{$RSM.TLD}]';
		$services->{$service}->{'key_80_ip'} = 'rsm.rdds.80.ip[{$RSM.TLD}]';

	}
	elsif ($service eq 'epp')
	{
		# TODO: max_value for EPP is based on the command
		$services->{$service}->{'delay'} = get_macro_epp_delay();
		$services->{$service}->{'valuemaps'} = get_valuemaps($service);
		$services->{$service}->{'minonline'} = get_macro_epp_probe_online();
		$services->{$service}->{'key_status'} = 'rsm.epp[{$RSM.TLD},';	# 0 - down, 1 - up
		$services->{$service}->{'key_ip'} = 'rsm.epp.ip[{$RSM.TLD}]';
		$services->{$service}->{'key_rtt'} = 'rsm.epp.rtt[{$RSM.TLD},';
	}

	$services->{$service}->{'key_avail'} = "rsm.slv.$service.avail";

	fail("$service delay (", $services->{$service}->{'delay'}, ") is not multiple of 60") unless ($services->{$service}->{'delay'} % 60 == 0);
}

my $now = opt('now') ? getopt('now') : time();

dbg("now:", ts_full($now));

# rolling week incidents
my ($rollweek_from, $rollweek_till) = get_rollweek_bounds($now);

my $tlds_ref;
if (opt('tld'))
{
	my @tlds = split(',', getopt('tld'));

	foreach my $t (@tlds)
	{
		fail("TLD $t does not exist.") if (tld_exists($t) == 0);
	}

	$tlds_ref = \@tlds;
}
else
{
	$tlds_ref = get_tlds();
}

my $servicedata;	# hash with various data of TLD service

my $probe_avail_limit = get_macro_probe_avail_limit();

my $config_minclock = __get_config_minclock();

dbg("config_minclock:$config_minclock");

# in order to make sure all availability data points are saved we need to go back extra minute
my $last_time_till = max_avail_time($now) - 60;

my ($check_from, $check_till, $continue_file);

if (opt('continue'))
{
	$continue_file = ah_get_continue_file();
	my $handle;

	if (! -e $continue_file)
	{
		$check_from = truncate_from($config_minclock);
	}
	else
	{
		fail("cannot open continue file $continue_file\": $!") unless (open($handle, '<', $continue_file));

		chomp(my @lines = <$handle>);

		close($handle);

		my $ts = $lines[0];

		my $next_ts = $ts + 1;	# continue with the next minute
		my $truncated_ts = truncate_from($next_ts);

		if ($truncated_ts != $next_ts)
		{
			wrn(sprintf("truncating last update value (%s) to %s", ts_str($ts), ts_str($truncated_ts)));
		}

		$check_from = $truncated_ts;

		info(sprintf("getting date from %s: %s (%d)", $continue_file, ts_str($check_from), $check_from));
	}

	if ($check_from == 0)
	{
		fail("no data from probes in the database yet");
	}

	if (opt('period'))
	{
		$check_till = $check_from + getopt('period') * 60 - 1;
	}
	else
	{
		$check_till = $last_time_till;
	}
}
elsif (opt('from'))
{
	$check_from = $opt_from;

	if (opt('period'))
	{
		$check_till = $check_from + getopt('period') * 60 - 1;
	}
	else
	{
		$check_till = $last_time_till;
	}
}
elsif (opt('period'))
{
	# only period specified
	$check_till = $last_time_till;
	$check_from = $check_till - getopt('period') * 60 + 1;
}

fail("cannot get the beginning of calculation period") unless(defined($check_from));
fail("cannot get the end of calculation period") unless(defined($check_till));

if ($check_till < $check_from)
{
	info("cannot yet calculate, the latest data not fully available");
	exit(0);
}

if ($check_till > $last_time_till)
{
	my $left = ($check_till - $last_time_till) / 60;
	my $left_str;

	if ($left == 1)
	{
		$left_str = "1 minute";
	}
	else
	{
		$left_str = "$left minutes";
	}

	wrn(sprintf("cannot yet calculate for selected period (%s), please wait for %s for the data to be processed", selected_period($check_from, $check_till), $left_str));

	exit(0);
}

my ($from, $till) = get_real_services_period($services, $check_from, $check_till);

if (!$from)
{
    info("no full test periods within specified time range: ", selected_period($check_from, $check_till));
    exit(0);
}

my $tlds_to_process = 0;
foreach (@$tlds_ref)
{
	$tlds_to_process++;

	last if (opt('limit') && $tlds_to_process == getopt('limit'));

	# NB! This is needed in order to set the value globally.
	$tld = $_;

	if (__tld_ignored($tld) == SUCCESS)
	{
		dbg("tld \"$tld\" found in IGNORE list");
		next;
	}

	my $readable_tld = get_readable_tld($tld);

	foreach my $service (keys(%{$services}))
	{
		if (tld_service_enabled($tld, $service) != SUCCESS)
		{
			$servicedata->{$tld}->{'services'}->{$service}->{'alarmed'} = AH_ALARMED_DISABLED;
			next;
		}

		my $rollweek_key = "rsm.slv.$service.rollweek";
		my $result;

		if (get_lastclock($tld, $rollweek_key, \$result) != SUCCESS)
		{
			wrn(uc($service), ": configuration error, item $rollweek_key not found");
			$servicedata->{$tld}->{'services'}->{$service}->{'alarmed'} = AH_ALARMED_DISABLED;
			next;
		}

		if (!$result->{'lastclock'})
		{
			wrn(uc($service), ": no rolling week data in the database yet");
			$servicedata->{$tld}->{'services'}->{$service}->{'alarmed'} = AH_ALARMED_DISABLED;
			next;
		}

		dbg("lastclock:", $result->{'lastclock'}, " lastvalue:", $result->{'lastvalue'});

		$servicedata->{$tld}->{'services'}->{$service}->{'rw_lastclock'} = $result->{'lastclock'};
		$servicedata->{$tld}->{'services'}->{$service}->{'rw_lastvalue'} = $result->{'lastvalue'};
		$servicedata->{$tld}->{'services'}->{$service}->{'rw_itemid'} = $result->{'itemid'};
	}
}

undef($tld);

my $all_probes_ref = get_probes();

if (opt('probe'))
{
	my $temp = $all_probes_ref;

	undef($all_probes_ref);

	$all_probes_ref->{getopt('probe')} = $temp->{getopt('probe')};
}

my $probe_times_ref = get_probe_times($from, $till, $probe_avail_limit, $all_probes_ref);

foreach (keys(%$servicedata))
{
	# NB! This is needed in order to set the value globally.
	$tld = $_;

	my $readable_tld = get_readable_tld($tld);

	my $tld_status->{'status'} = AH_STATUS_UP;

	my $dns_tests_ref;

	foreach my $service (keys(%{$servicedata->{$tld}->{'services'}}))
	{
		my $rw_lastclock = $servicedata->{$tld}->{'services'}->{$service}->{'rw_lastclock'};
		my $rw_lastvalue = $servicedata->{$tld}->{'services'}->{$service}->{'rw_lastvalue'};
		my $rw_itemid = $servicedata->{$tld}->{'services'}->{$service}->{'rw_itemid'};
		my $alarmed = $servicedata->{$tld}->{'services'}->{$service}->{'alarmed'};

		my $delay = $services->{$service}->{'delay'};
		my $service_from = $services->{$service}->{'from'};
		my $service_till = $services->{$service}->{'till'};
		my $key_avail = $services->{$service}->{'key_avail'};

		if (defined($alarmed) && $alarmed eq AH_ALARMED_DISABLED)
		{
			$tld_status->{'services'}->{$service}->{'enabled'} = AH_ENABLED_NO;

			if (opt('dry-run'))
			{
				__prnt(uc($service), ' alarmed:', AH_ALARMED_DISABLED);
			}
			else
			{
				if (ah_save_alarmed($readable_tld, $service, AH_ALARMED_DISABLED) != AH_SUCCESS)
				{
					fail("cannot save alarmed: ", ah_get_error());
				}
			}

			next;
		}

		if (!$service_from || !$service_till)
		{
			# this is not the time to calculate the service yet,
			# it will be done in a later runs
			next;
		}

		my $hostid = get_hostid($tld);

		my $avail_itemid = get_itemid_by_hostid($hostid, $key_avail);
		if (!$avail_itemid)
		{
			wrn("configuration error: ", rsm_slv_error());
			next;
		}

		my $rollweek;

		# we must have a special calculation key for that ($rollweek_key)
		#my $downtime = get_downtime($avail_itemid, $rollweek_from, $rollweek_till);

		if ($rw_lastclock)
		{
			$rollweek = $rw_lastvalue;
		}
		else
		{
			# try go get it from history
			if (__get_rollweek($rw_itemid, $now, $delay, \$rollweek) != SUCCESS)
			{
				wrn("no $service rolling week value in the database, using 0");
				$rollweek = 0.0;
			}
		}

		__prnt(uc($service), " period: ", selected_period($service_from, $service_till)) if (opt('dry-run') || opt('debug'));

		if (opt('dry-run'))
		{
			__prnt(uc($service), " service availability $rollweek (", ts_str($rw_lastclock), ")");
		}
		else
		{
			if (ah_save_service_availability($readable_tld, $service, $rollweek) != AH_SUCCESS)
			{
				fail("cannot save service availability: ", ah_get_error());
			}
		}

		dbg("getting current $service availability (delay:$delay)");

		# get availability
		my $incidents = get_incidents2($avail_itemid, $delay, $now);

		$alarmed = AH_ALARMED_NO;
		if (scalar(@$incidents) != 0)
		{
			if ($incidents->[0]->{'false_positive'} == 0 && !defined($incidents->[0]->{'end'}))
			{
				$alarmed = AH_ALARMED_YES;
			}
		}

		if (opt('dry-run'))
		{
			__prnt(uc($service), " alarmed:$alarmed");
		}
		else
		{
			if (ah_save_alarmed($readable_tld, $service, $alarmed) != AH_SUCCESS)
			{
				fail("cannot save alarmed: ", ah_get_error());
			}
		}

		$tld_status->{'services'}->{$service}->{'enabled'} = AH_ENABLED_YES;
		$tld_status->{'services'}->{$service}->{'status'} = (($alarmed eq AH_ALARMED_YES) ? AH_STATUS_DOWN : AH_STATUS_UP);

		my ($nsips_ref, $dns_items_ref, $rdds_dbl_items_ref, $rdds_str_items_ref, $epp_dbl_items_ref, $epp_str_items_ref);

		if ($service eq 'dns' || $service eq 'dnssec')
		{
			$nsips_ref = get_nsips($tld, $services->{$service}->{'key_rtt'}, 1);	# templated
			$dns_items_ref = get_dns_itemids($nsips_ref, $services->{$service}->{'key_rtt'}, $tld, getopt('probe'));
		}
		elsif ($service eq 'rdds')
		{
			$rdds_dbl_items_ref = get_rdds_dbl_itemids($tld, getopt('probe'), $services->{'rdds'}->{'key_43_rtt'}, $services->{'rdds'}->{'key_80_rtt'}, $services->{'rdds'}->{'key_43_upd'});
			$rdds_str_items_ref = get_rdds_str_itemids($tld, getopt('probe'), $services->{'rdds'}->{'key_43_ip'}, $services->{'rdds'}->{'key_80_ip'});
		}
		elsif ($service eq 'epp')
		{
			$epp_dbl_items_ref = get_epp_dbl_itemids($tld, getopt('probe'), $services->{'epp'}->{'key_rtt'});
			$epp_str_items_ref = get_epp_str_itemids($tld, getopt('probe'), $services->{'epp'}->{'key_ip'});
		}

		$incidents = get_incidents2($avail_itemid, $delay, $service_from, $service_till);

		foreach (@$incidents)
		{
			my $eventid = $_->{'eventid'};
			my $event_start = $_->{'start'};
			my $event_end = $_->{'end'};
			my $false_positive = $_->{'false_positive'};

			my $start = $event_start;
			my $end = $event_end;

			if (defined($service_from) && $service_from > $event_start)
			{
				$start = $service_from;
			}

			if (defined($service_till))
			{
				if (!defined($event_end) || (defined($event_end) && $service_till < $event_end))
				{
					$end = $service_till;
				}
			}

			# get results within incidents
			my $rows_ref = db_select(
				"select value,clock".
				" from history_uint".
				" where itemid=$avail_itemid".
					" and ".sql_time_condition($start, $end).
				" order by itemid,clock");

			my $cycles;

			my $status_up = 0;
			my $status_down = 0;

			my $values_from;
			my $values_till;

			foreach my $row_ref (@$rows_ref)
			{
				my $value = $row_ref->[0];
				my $clock = $row_ref->[1];

				my $cycleclock = cycle_start($clock, $delay);

				# We have the test resulting value (Up or Down) at "clock". Now we need to select the
				# time bounds (start/end) of all data points from all proxies.
				#
				#   +........................period (service delay)...........................+
				#   |                                                                         |
				# start                                 clock                                end
				#   |.....................................|...................................|
				#   0 seconds <--zero or more minutes--> 30                                  59
				#
				my $end = $cycleclock + $delay - 1;

				$values_from = $cycleclock if (!$values_from || $cycleclock < $values_from);
				$values_till = $end if (!$values_till || $end > $values_till);

				if (opt('dry-run'))
				{
					if ($value == UP)
					{
						$status_up++;
					}
					elsif ($value == DOWN)
					{
						$status_down++;
					}
					else
					{
						wrn("unknown status: $value (expected UP (" . UP . ") or DOWN (" . DOWN . "))");
					}
				}

				$cycles->{$cycleclock}->{'tld'} = $tld;
				$cycles->{$cycleclock}->{'status'} = get_result_string($cfg_dns_statusmaps, $value);
			}

			if (!$values_from)
			{
				wrn("$service: no results within incident (id:$eventid clock:$event_start)");
				last;
			}

			if (opt('dry-run'))
			{
				__prnt(uc($service), " incident id:$eventid start:", ts_str($event_start), " end:" . ($event_end ? ts_str($event_end) : "ACTIVE") . " fp:$false_positive");
				__prnt(uc($service), " tests successful:$status_up failed:$status_down");
			}
			else
			{
				if (ah_save_incident_state($readable_tld, $service, $eventid, $event_start, $event_end, $false_positive) != AH_SUCCESS)
				{
					fail("cannot save incident state: ", ah_get_error());
				}
			}

			my $tests_ref;

			if ($service eq 'dns' || $service eq 'dnssec')
			{
				if (!$dns_tests_ref)
				{
					$dns_tests_ref = get_dns_test_values($dns_items_ref, $values_from, $values_till,
						$services->{$service}->{'valuemaps'}, $delay, $service);
				}

				$tests_ref = $dns_tests_ref;
			}
			elsif ($service eq 'rdds')
			{
				$tests_ref = get_rdds_test_values($rdds_dbl_items_ref, $rdds_str_items_ref,
					$values_from, $values_till, $services->{$service}->{'valuemaps'}, $delay);
			}

			# add results to appropriate cycles
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

			# add probes that are missing results
			foreach my $probe (keys(%$all_probes_ref))
			{
				foreach my $cycleclock (keys(%$cycles))
				{
					my $found = 0;

					foreach my $interface (keys(%{$cycles->{$cycleclock}->{'interfaces'}}))
					{
						foreach my $cycle_probe (keys(%{$cycles->{$cycleclock}->{'interfaces'}->{$interface}->{'probes'}}))
						{
							if ($cycle_probe eq $probe)
							{
								dbg("\"$cycle_probe\" found!");

								$found = 1;
								last;
							}
						}

						$cycles->{$cycleclock}->{'interfaces'}->{$interface}->{'probes'}->{$probe}->{'status'} = PROBE_NORESULT_STR if ($found == 0);
					}
				}
			}

			# get results from probes, working services: (dns: number of NS, rdds: 43, 80)
			my $itemids_ref = get_service_status_itemids($tld, $services->{$service}->{'key_status'});
			my $probe_results_ref = get_probe_results($itemids_ref, $values_from, $values_till);

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

				# create the needed JSON format
				my $cycle_hash = __create_cycle_hash($tld, $cycleclock, $cycles->{$cycleclock}, $services->{$service});

				if (opt('dry-run'))
				{
					__prnt_json($cycle_hash);
				}
				else
				{
					if (ah_save_incident_results($readable_tld, $service, $eventid, $event_start, $cycle_hash, $cycleclock) != AH_SUCCESS)
					{
						fail("cannot save incident results: ", ah_get_error());
					}
				}
			}
			# elsif ($service eq 'epp')
			# {
			# 	my $tests_ref = get_epp_test_values($epp_dbl_items_ref, $epp_str_items_ref, $values_from, $values_till);

			# 	foreach my $probe (keys(%$tests_ref))
			# 	{
			# 		my $cycles_idx = 0;

			# 		foreach my $clock (sort(keys(%{$tests_ref->{$probe}})))	# must be sorted by clock
			# 		{
			# 			if ($clock < $cycles[$cycles_idx]->{'start'})
			# 			{
			# 				no_cycle_result($service, $key_avail, $probe, $clock);
			# 				next;
			# 			}

			# 			# move to corresponding test result
			# 			$cycles_idx++ while ($cycles_idx < $cycles_count && $clock > $cycles[$cycles_idx]->{'end'});

			# 			if ($cycles_idx == $cycles_count)
			# 			{
			# 				no_cycle_result($service, $key_avail, $probe, $clock);
			# 				next;
			# 			}

			# 			my $cycle_ref = $cycles[$cycles_idx];

			# 			$cycle_ref->{'probes'}->{$probe}->{'status'} = undef;	# the status is set later

			# 			if (probe_offline_at($probe_times_ref, $probe, $clock) != 0)
			# 			{
			# 				$cycle_ref->{'probes'}->{$probe}->{'status'} = PROBE_OFFLINE_STR;
			# 			}
			# 			else
			# 			{
			# 				foreach my $type (keys(%{$tests_ref->{$probe}->{$clock}}))
			# 				{
			# 					$tests_ref->{$probe}->{$clock}->{$type} = get_detailed_result($services->{$service}->{'valuemaps'}, $tests_ref->{$probe}->{$clock}->{$type});
			# 				}

			# 				$cycle_ref->{'probes'}->{$probe}->{'details'}->{$clock} = $tests_ref->{$probe}->{$clock};
			# 			}
			# 		}
			# 	}

			# 	# add probes that are missing results
			# 	foreach my $probe (keys(%$all_probes_ref))
			# 	{
			# 		foreach my $cycle_ref (@cycles)
			# 		{
			# 			my $found = 0;

			# 			foreach my $cycle_ref_probe (keys(%{$cycle_ref->{'probes'}}))
			# 			{
			# 				if ($cycle_ref_probe eq $probe)
			# 				{
			# 					dbg("\"$cycle_ref_probe\" found!");

			# 					$found = 1;
			# 					last;
			# 				}
			# 			}

			# 			$cycle_ref->{'probes'}->{$probe}->{'status'} = PROBE_NORESULT_STR if ($found == 0);
			# 		}
			# 	}

			# 	# get results from probes: EPP down (0) or up (1)
			# 	my $itemids_ref = get_service_status_itemids($tld, $services->{$service}->{'key_status'});
                        #         my $probe_results_ref = get_probe_results($itemids_ref, $values_from, $values_till);

			# 	foreach my $cycle_ref (@cycles)
                        #         {
			# 		# set status
			# 		my $cycle_start = $cycle_ref->{'start'};
			# 		my $cycle_end = $cycle_ref->{'end'};

			# 		delete($cycle_ref->{'start'});
			# 		delete($cycle_ref->{'end'});

			# 		foreach my $probe (keys(%{$cycle_ref->{'probes'}}))
			# 		{
			# 			foreach my $probe_result_ref (@{$probe_results_ref->{$probe}})
			# 			{
			# 				next if ($probe_result_ref->{'clock'} < $cycle_start);
			# 				last if ($probe_result_ref->{'clock'} > $cycle_end);

			# 				if (!defined($cycle_ref->{'probes'}->{$probe}->{'status'}))
			# 				{
			# 					$cycle_ref->{'probes'}->{$probe}->{'status'} = ($probe_result_ref->{'value'} == 1 ? "Up" : "Down");
			# 				}
			# 			}
			# 		}

			# 		if (opt('dry-run'))
			# 		{
			# 			__prnt_json($cycle_ref);
			# 		}
			# 		else
			# 		{
			# 			if (ah_save_incident_results($readable_tld, $service, $eventid, $event_start, $cycle_ref, $cycle_ref->{'clock'}) != AH_SUCCESS)
			# 			{
			# 				fail("cannot save incident results: ", ah_get_error());
			# 			}
			# 		}
			# 	}
			# }
			# else
			# {
			# 	fail("THIS SHOULD NEVER HAPPEN (unknown service \"$service\")");
			# }
		}

		# if we are here the service is enabled, check tld status
		if ($tld_status->{'services'}->{$service}->{'status'} eq AH_STATUS_DOWN)
		{
			$tld_status->{'status'} = AH_STATUS_DOWN;
		}

		$tld_status->{'services'}->{$service}->{'emergencyThreshold'} = $rollweek;

		# rolling week incidents
		my $rw_incidents = get_incidents2($avail_itemid, $delay, $rollweek_from, $rollweek_till);

		foreach (@$incidents)
		{
			my $hash =
			{
				'incidentID' => $_->{'start'} . '.' . $_->{'eventid'},
				'startTime' => $_->{'start'},
				'falsePositive' => ($_->{'false_positive'} == 0 ? AH_FALSE_POSITIVE_FALSE : AH_FALSE_POSITIVE_TRUE),
				'state' => ($_->{'end'} ? AH_INCIDENT_ENDED : AH_INCIDENT_ACTIVE),
				'endTime' => $_->{'end'}
			};

			push(@{$tld_status->{'services'}->{$service}->{'incidents'}}, $hash);
		}
	}

	if (opt('dry-run'))
	{
		__prnt("status: ", $tld_status->{'status'});
	}
	else
	{
		if (ah_save_tld_status($readable_tld, $tld_status) != AH_SUCCESS)
		{
			fail("cannot save TLD status: ", ah_get_error());
		}
	}
}
# unset TLD (for the logs)
$tld = undef;

if (defined($continue_file) && !opt('dry-run'))
{
	unless (write_file($continue_file, $till) == SUCCESS)
	{
		wrn("cannot update continue file \"$continue_file\": $!");
		next;
	}

	dbg("last update: ", ts_str($till));
}

unless (opt('dry-run') || opt('tld'))
{
	__update_false_positives();
}

slv_exit(SUCCESS);

sub __prnt
{
	print((defined($tld) ? "$tld: " : ''), join('', @_), "\n");
}

sub __prnt_json
{
	my $cycle_ref = shift;

	if (opt('debug'))
	{
		dbg(ah_encode_pretty_json($cycle_ref), "-----------------------------------------------------------");
	}
	else
	{
		__prnt(ts_str($cycle_ref->{'clock'}), " ", $cycle_ref->{'status'});
	}
}

sub __tld_ignored
{
	my $tld = shift;

	return SUCCESS if (exists($ignore_hash{$tld}));

	return E_FAIL;
}

sub __update_false_positives
{
	# now check for possible false_positive change in front-end
	my $last_audit = ah_get_last_audit();
	my $maxclock = 0;

	my $rows_ref = db_select(
		"select details,max(clock)".
		" from auditlog".
		" where resourcetype=".AUDIT_RESOURCE_INCIDENT.
			" and clock>$last_audit".
		" group by details");

	foreach my $row_ref (@$rows_ref)
	{
		my $details = $row_ref->[0];
		my $clock = $row_ref->[1];

		# ignore old "details" format (dropped in December 2014)
		next if ($details =~ '.*Incident \[.*\]');

		my $eventid = $details;
		$eventid =~ s/^([0-9]+): .*/$1/;

		$maxclock = $clock if ($clock > $maxclock);

		my $rows_ref2 = db_select("select objectid,clock,false_positive from events where eventid=$eventid");

		fail("cannot get event with ID $eventid") unless (scalar(@$rows_ref2) == 1);

		my $triggerid = $rows_ref2->[0]->[0];
		my $event_clock = $rows_ref2->[0]->[1];
		my $false_positive = $rows_ref2->[0]->[2];

		my ($tld, $service) = get_tld_by_trigger($triggerid);

		dbg("auditlog message: $eventid\t$service\t".ts_str($event_clock)."\t".ts_str($clock)."\tfp:$false_positive\t$tld\n");

		if (ah_save_false_positive($tld, $service, $event_clock, $eventid, $false_positive, $clock) != AH_SUCCESS)
		{
			wrn("cannot update false_positive value of event (eventid:$eventid start:[", ts_full($event_clock), "] service:$service false_positive:$false_positive clock:[", ts_full($clock), "): ",
				ah_get_error());
		}
	}

	ah_save_audit($maxclock) unless ($maxclock == 0);
}

sub __validate_input
{
	if (opt('service'))
	{
		if (getopt('service') ne 'dns' && getopt('service') ne 'dnssec' && getopt('service') ne 'rdds' && getopt('service') ne 'epp')
		{
			print("Error: \"", getopt('service'), "\" - unknown service\n");
			usage();
		}
	}

	if (opt('tld') && opt('ignore-file'))
	{
		print("Error: options --tld and --ignore-file cannot be used together\n");
		usage();
	}

	if (opt('continue') && opt('from'))
        {
                print("Error: options --continue and --from cannot be used together\n");
                usage();
        }

	if (opt('probe'))
	{
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
			print("Error: unknown probe \"$probe\"\n");
			print("\nAvailable probes:\n");
			foreach my $name (keys(%$probes_ref))
			{
				print("  $name\n");
			}
			exit(E_FAIL);
		}
        }
}

sub __sql_arr_to_str
{
	my $rows_ref = shift;

	my @arr;
	foreach my $row_ref (@$rows_ref)
        {
                push(@arr, $row_ref->[0]);
	}

	return join(',', @arr);
}

sub __get_min_clock
{
	my $tld = shift;
	my $service = shift;
	my $config_minclock = shift;

	my $key_condition;
	if ($service eq 'dns' || $service eq 'dnssec' || $service eq 'epp')
	{
		$key_condition = "key_='" . $services->{$service}->{'key_status'} . "'";
	}
	elsif ($service eq 'rdds')
	{
		$key_condition = "key_ like '" . $services->{$service}->{'key_status'} . "%'";
	}

	my $rows_ref = db_select("select hostid from hosts where host like '$tld %'");

	return 0 if (scalar(@$rows_ref) == 0);

	my $hostids_str = __sql_arr_to_str($rows_ref);

	$rows_ref = db_select("select itemid from items where $key_condition and templateid is not NULL and hostid in ($hostids_str)");

	return 0 if (scalar(@$rows_ref) == 0);

	my $itemids_str = __sql_arr_to_str($rows_ref);

	$rows_ref = db_select("select min(clock) from history_uint where itemid in ($itemids_str) and clock<$config_minclock");

	return $rows_ref->[0]->[0] ? $rows_ref->[0]->[0] : $config_minclock;
}

sub __get_config_minclock
{
	my $config_key = 'rsm.configvalue[RSM.SLV.DNS.TCP.RTT]';

	# Get the minimum clock from the item that is collected once a day, this way
	# "min(clock)" won't take too much time (see function __get_min_clock() for details).
	my $rows_ref = db_select("select itemid from items where key_='$config_key'");

	fail("item $config_key not found in Zabbix configuration") unless (scalar(@$rows_ref) == 1);

	my $config_itemid = $rows_ref->[0]->[0];

	$rows_ref = db_select("select min(clock) from history_uint where itemid=$config_itemid");

	my $minclock = $rows_ref->[0]->[0];

	fail("no data in the database yet") unless ($minclock);

	return $minclock;
}

sub __get_rollweek
{
	my $rw_itemid = shift;
	my $clock = shift;
	my $delay = shift;
	my $result_ref = shift;

	$clock = time() unless($clock);

	my ($from, $till) = get_interval_bounds($delay, $clock);

	my $rows_ref = db_select(
		"select value".
		" from history".
		" where itemid=$rw_itemid".
			" and ".sql_time_condition($from, $till).
		" order by itemid,clock");

	return E_FAIL if (scalar(@$rows_ref) == 0);

	$$result_ref = $rows_ref->[0]->[0];

	return SUCCESS;
}

sub __create_cycle_hash
{
	my $tld = shift;
	my $cycleclock = shift;
	my $cycle_ref = shift;
	my $service_ref = shift;

	my $cycle =
	{
		'tld' => $tld,
		'clock' => $cycleclock,
		'status' => $cycle_ref->{'status'}
	};

	foreach my $interface (keys(%{$cycle_ref->{'interfaces'}}))
	{
		my $interface_ref;

		$interface_ref->{'interface'} = $interface;
		$interface_ref->{'status'} = undef;

		# TODO: in case of DNSSEC the interface status and status on every probe is currently
		# not supported, we need to calculate these manually.

		my $test_total_probes = 0;
		my $test_success_probes = 0;
		my $test_probes_online = 0;

		foreach my $probe (keys(%{$cycle_ref->{'interfaces'}->{$interface}->{'probes'}}))
		{
			my $probe_ref;

			$probe_ref->{'city'} = $probe;
			$probe_ref->{'status'} = $cycle_ref->{'interfaces'}->{$interface}->{'probes'}->{$probe}->{'status'};

			my $dnssec_success_ns = 0;

			$test_probes_online++ if (defined($probe_ref->{'status'}) && $probe_ref->{'status'} ne PROBE_OFFLINE_STR);

			foreach my $target (keys(%{$cycle_ref->{'interfaces'}->{$interface}->{'probes'}->{$probe}->{'targets'}}))
			{
				my $status = AH_STATUS_UP;
				foreach my $metric_ref (@{$cycle_ref->{'interfaces'}->{$interface}->{'probes'}->{$probe}->{'targets'}->{$target}})
				{
					# TODO: for EPP check 'upd' field here
					if (__check_test($interface, $metric_ref->{'rtt'}, $metric_ref->{'description'}, $service_ref->{'max_value'}) != SUCCESS)
					{
						$status = AH_STATUS_DOWN;
						last;
					}
				}

				if ($interface eq JSON_INTERFACE_DNSSEC)
				{
					$dnssec_success_ns++ if ($status eq AH_STATUS_UP);
				}

				my $test_data_ref;

				$test_data_ref->{'target'} = $target;
				$test_data_ref->{'status'} = $status;
				$test_data_ref->{'metrics'} = $cycle_ref->{'interfaces'}->{$interface}->{'probes'}->{$probe}->{'targets'}->{$target};

				push(@{$probe_ref->{'testData'}}, $test_data_ref);
			}

			if ($interface eq JSON_INTERFACE_DNSSEC)
			{
				$probe_ref->{'status'} = ($dnssec_success_ns >= $service_ref->{'minns'} ? AH_STATUS_UP : AH_STATUS_DOWN);
			}

			if ($interface eq JSON_INTERFACE_DNSSEC || $interface eq JSON_INTERFACE_RDDS43 || $interface eq JSON_INTERFACE_RDDS80)
			{
				$test_success_probes++ if ($probe_ref->{'status'} eq AH_STATUS_UP);

				$test_total_probes++;
			}

			push(@{$interface_ref->{'probes'}}, $probe_ref);
		}

		if ($interface eq JSON_INTERFACE_DNSSEC || $interface eq JSON_INTERFACE_RDDS43 || $interface eq JSON_INTERFACE_RDDS80)
		{
			my $interface_status;

			if ($test_probes_online < $service_ref->{'minonline'})
			{
				$interface_status = AH_STATUS_UP;	# TODO: indicate that the interface is up only because not available probes online
			}
			else
			{
				my $perc = $test_success_probes * 100 / $test_total_probes;

				if ($perc > SLV_UNAVAILABILITY_LIMIT)
				{
					$interface_status = AH_STATUS_UP;
				}
				else
				{
					$interface_status = AH_STATUS_DOWN;
				}
			}

			$interface_ref->{'status'} = $interface_status;

			if ($interface eq JSON_INTERFACE_DNSSEC)
			{
				$cycle->{'status'} = $interface_status;
			}
		}
		elsif ($interface eq JSON_INTERFACE_DNS)
		{
			$interface_ref->{'status'} = $cycle->{'status'};
		}

		push(@{$cycle->{'testedInterface'}}, $interface_ref);
	}

	return $cycle;
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
		$status = ($value >= $service_ref->{'minns'} ? AH_STATUS_UP : AH_STATUS_DOWN);
	}
	elsif ($interface eq JSON_INTERFACE_DNSSEC)
	{
		# TODO: dnssec status on a particular probe is not supported currently,
		# make this calculation in function __create_cycle_hash() for now.
	}
	elsif ($interface eq JSON_INTERFACE_RDDS43 || $interface eq JSON_INTERFACE_RDDS80)
	{
		my $service_only = ($interface eq JSON_INTERFACE_RDDS43 ? 2 : 3);	# 0 - down, 1 - up, 2 - only 43, 3 - only 80

		$status = (($value == 1 || $value == $service_only) ? AH_STATUS_UP : AH_STATUS_DOWN);
	}
	else
	{
		fail("$interface: unsupported interface");
	}

	return $status;
}

__END__

=head1 NAME

update-api-data.pl - save information about the incidents to a filesystem

=head1 SYNOPSIS

update-api-data.pl [--service <dns|dnssec|rdds|epp>] [--tld <tld>|--ignore-file <file>] [--from <timestamp>|--continue] [--period minutes] [--dry-run [--probe name]] [--warnslow <seconds>] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--service> service

Process only specified service. Service must be one of: dns, dnssec, rdds or epp.

=item B<--tld> tld

Process only specified TLD. If not specified all TLDs will be processed.

This option cannot be used together with option --ignore-file.

=item B<--ignore-file> file

Specify file containing the list of TLDs that should be ignored. TLDs are specified one per line.

This option cannot be used together with option --tld.

=item B<--period> minutes

Specify number minutes of the period to handle during this run. The first cycle to handle can be specified
using options --from or --continue (continue from the last time when --continue was used) (see below).

=item B<--from> timestamp

Specify Unix timestamp within the oldest test cycle to handle in this run. You don't need to specify the
first second of the test cycle, any timestamp within it will work. Number of test cycles to handle within
this run can be specified using option --period otherwise all completed test cycles available in the
database up till now will be handled.

This option cannot be used together with option --continue.

=item B<--continue>

Continue calculation from the timestamp of the last run with --continue. In case of first run with
--continue the oldest available data will be used as starting point. You may specify the end point
of the period with --period option (see above). If you don't specify the end point the timestamp
of the newest available data in the database will be used.

Note, that continue token is not updated if this option was specified together with --dry-run or when you use
--from option.

=item B<--probe> name

Only calculate data from specified probe.

This option can only be used for debugging purposes and must be used together with option --dry-run .

=item B<--now> timestamp

Run script as if current time would be as specified.

=item B<--base> directory

Specify different base directory (default /opt/zabbix/sla).

=item B<--dry-run>

Print data to the screen, do not write anything to the filesystem.

=item B<--warnslow> seconds

Issue a warning in case an SQL query takes more than specified number of seconds. A floating-point number
is supported as seconds (i. e. 0.5, 1, 1.5 are valid).

=item B<--debug>

Run the script in debug mode. This means printing more information.

=item B<--help>

Print a brief help message and exit.

=back

=head1 DESCRIPTION

B<This program> will run through all the incidents found at optionally specified time bounds
and store details about each on the filesystem. This information will be used by external
program to provide it for users in convenient way.

=head1 EXAMPLES

./update-api-data.pl --tld example --period 10

This will update API data of the last 10 minutes of DNS, DNSSEC, RDDS and EPP services of TLD example.

=cut
