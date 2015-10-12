#!/usr/bin/perl -w

use lib '/opt/zabbix/scripts';

use strict;
use warnings;
use RSM;
use RSMSLV;
use ApiHelper;
use JSON::XS;

use constant JSON_RDDS_SUBSERVICE => 'subService';

use constant AUDIT_RESOURCE_INCIDENT => 32;

parse_opts('tld=s', 'service=s', 'period=n', 'from=n', 'continue!', 'ignore-file=s', 'probe=s', 'limit=n');

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

my $opt_from = getopt('from');

if (defined($opt_from))
{
	$opt_from = truncate_from($opt_from);	# use the whole minute
	dbg("option \"from\" truncated to the start of a minute: $opt_from") if ($opt_from != getopt('from'));
}

my %services;
if (opt('service'))
{
	$services{lc(getopt('service'))} = undef;
}
else
{
	foreach my $service ('dns', 'dnssec', 'rdds', 'epp')
	{
		$services{$service} = undef;
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

my $cfg_dns_statusmaps = get_statusmaps('dns');

foreach my $service (keys(%services))
{
	if ($service eq 'dns' || $service eq 'dnssec')
	{
		if (!$cfg_dns_delay)
		{
			$cfg_dns_delay = get_macro_dns_udp_delay();
			$cfg_dns_minns = get_macro_minns();
			$cfg_dns_valuemaps = get_valuemaps('dns');
		}

		$services{$service}{'delay'} = $cfg_dns_delay;
		$services{$service}{'minns'} = $cfg_dns_minns;
		$services{$service}{'valuemaps'} = $cfg_dns_valuemaps;
		$services{$service}{'key_status'} = 'rsm.dns.udp[{$RSM.TLD}]'; # 0 - down, 1 - up
		$services{$service}{'key_rtt'} = 'rsm.dns.udp.rtt[{$RSM.TLD},';
	}
	elsif ($service eq 'rdds')
	{
		$services{$service}{'delay'} = get_macro_rdds_delay();
		$services{$service}{'valuemaps'} = get_valuemaps($service);
		$services{$service}{'key_status'} = 'rsm.rdds[{$RSM.TLD}'; # 0 - down, 1 - up, 2 - only 43, 3 - only 80
		$services{$service}{'key_43_rtt'} = 'rsm.rdds.43.rtt[{$RSM.TLD}]';
		$services{$service}{'key_43_ip'} = 'rsm.rdds.43.ip[{$RSM.TLD}]';
		$services{$service}{'key_43_upd'} = 'rsm.rdds.43.upd[{$RSM.TLD}]';
		$services{$service}{'key_80_rtt'} = 'rsm.rdds.80.rtt[{$RSM.TLD}]';
		$services{$service}{'key_80_ip'} = 'rsm.rdds.80.ip[{$RSM.TLD}]';

	}
	elsif ($service eq 'epp')
	{
		$services{$service}{'delay'} = get_macro_epp_delay();
		$services{$service}{'valuemaps'} = get_valuemaps($service);
		$services{$service}{'key_status'} = 'rsm.epp[{$RSM.TLD},'; # 0 - down, 1 - up
		$services{$service}{'key_ip'} = 'rsm.epp.ip[{$RSM.TLD}]';
		$services{$service}{'key_rtt'} = 'rsm.epp.rtt[{$RSM.TLD},';
	}

	$services{$service}{'avail_key'} = "rsm.slv.$service.avail";

	fail("$service delay (", $services{$service}{'delay'}, ") is not multiple of 60") unless ($services{$service}{'delay'} % 60 == 0);
}

my $now = time();

my $tlds_ref;
if (opt('tld'))
{
	fail("TLD ", getopt('tld'), " does not exist.") if (tld_exists(getopt('tld')) == 0);

	$tlds_ref = [ getopt('tld') ];
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

dbg("check_from:", ts_full($check_from), " check_till:", ts_full($check_till), " last_time_till:", ts_full($last_time_till));

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

my ($from, $till) = get_real_services_period(\%services, $check_from, $check_till);

if (!$from)
{
    info("no full test periods within specified time range: ", selected_period($check_from, $check_till));
    exit(0);
}

my $tlds_processed = 0;
foreach (@$tlds_ref)
{
	$tlds_processed++;

	last if (opt('limit') && $tlds_processed == getopt('limit'));

	# NB! This is needed in order to set the value globally.
	$tld = $_;

	if (__tld_ignored($tld) == SUCCESS)
	{
		dbg("tld \"$tld\" found in IGNORE list");
		next;
	}

	my $ah_tld = ah_get_api_tld($tld);

	foreach my $service (keys(%services))
	{
		if (tld_service_enabled($tld, $service) != SUCCESS)
		{
			if (opt('dry-run'))
			{
				__prnt(uc($service), " DISABLED");
			}
			else
			{
				if (ah_save_alarmed($ah_tld, $service, AH_ALARMED_DISABLED) != AH_SUCCESS)
				{
					fail("cannot save alarmed: ", ah_get_error());
				}
			}

			next;
		}

		my $lastclock_key = "rsm.slv.$service.rollweek";

		my $lastclock = get_lastclock($tld, $lastclock_key);

		if ($lastclock == E_FAIL)
		{
			wrn(uc($service), ": configuration error, item $lastclock_key not found");
			next;
		}

		if ($lastclock == 0)
		{
			wrn(uc($service), ": no rolling week data in the database yet");

			if (opt('dry-run'))
			{
				__prnt(uc($service), " DISABLED");
			}
			else
			{
				if (ah_save_alarmed($ah_tld, $service, AH_ALARMED_DISABLED) != AH_SUCCESS)
				{
					fail("cannot save alarmed: ", ah_get_error());
				}
			}

			next;
		}

		dbg("lastclock:$lastclock");

		$servicedata->{$tld}->{$service}->{'lastclock'} = $lastclock;
	}
}

__prnt(sprintf("getting probe statuses for period: %s", selected_period($from, $till)));

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

	my $ah_tld = ah_get_api_tld($tld);

	foreach my $service (keys(%{$servicedata->{$tld}}))
	{
		my $lastclock = $servicedata->{$tld}->{$service}->{'lastclock'};

		my $delay = $services{$service}{'delay'};
		my $service_from = $services{$service}{'from'};
		my $service_till = $services{$service}{'till'};
		my $avail_key = $services{$service}{'avail_key'};

		if (!$service_from || !$service_till)
		{
			# this is not the time to calculate the service yet,
			# it will be done in a later runs
			next;
		}

		my $hostid = get_hostid($tld);
		my $avail_itemid = get_itemid_by_hostid($hostid, $avail_key);

		if ($avail_itemid < 0)
		{
			if ($avail_itemid == E_ID_NONEXIST)
			{
				wrn("configuration error: service $service enabled but item \"$avail_key\" not found");
			}
			elsif ($avail_itemid == E_ID_MULTIPLE)
			{
				wrn("configuration error: multiple items with key \"$avail_key\" found");
			}
			else
			{
				wrn("cannot get ID of $service item ($avail_key): unknown error");
			}

			next;
		}

		# we need down time in minutes, not percent, that's why we can't use "rsm.slv.$service.rollweek" value
		my ($rollweek_from, $rollweek_till) = get_rollweek_bounds();
		my $downtime = get_downtime($avail_itemid, $rollweek_from, $rollweek_till);

		__prnt(uc($service), " period: ", selected_period($service_from, $service_till)) if (opt('dry-run') or opt('debug'));

		if (opt('dry-run'))
		{
			__prnt(uc($service), " service availability $downtime (", ts_str($lastclock), ")");
		}
		else
		{
			if (ah_save_service_availability($ah_tld, $service, $downtime, $lastclock) != AH_SUCCESS)
			{
				fail("cannot save service availability: ", ah_get_error());
			}
		}

		dbg("getting current $service availability (delay:$delay)");

		# get availability
		my $incidents = get_incidents($avail_itemid, $now);

		my $alarmed_status = AH_ALARMED_NO;
		if (scalar(@$incidents) != 0)
		{
			if ($incidents->[0]->{'false_positive'} == 0 and not defined($incidents->[0]->{'end'}))
			{
				$alarmed_status = AH_ALARMED_YES;
			}
		}

		if (opt('dry-run'))
		{
			__prnt(uc($service), " alarmed:$alarmed_status");
		}
		else
		{
			if (ah_save_alarmed($ah_tld, $service, $alarmed_status, $lastclock) != AH_SUCCESS)
			{
				fail("cannot save alarmed: ", ah_get_error());
			}
		}

		my ($nsips_ref, $dns_items_ref, $rdds_dbl_items_ref, $rdds_str_items_ref, $epp_dbl_items_ref, $epp_str_items_ref);

		if ($service eq 'dns' || $service eq 'dnssec')
		{
			$nsips_ref = get_nsips($tld, $services{$service}{'key_rtt'}, 1);	# templated
			$dns_items_ref = get_dns_itemids($nsips_ref, $services{$service}{'key_rtt'}, $tld, getopt('probe'));
		}
		elsif ($service eq 'rdds')
		{
			$rdds_dbl_items_ref = get_rdds_dbl_itemids($tld, getopt('probe'), $services{'rdds'}{'key_43_rtt'}, $services{'rdds'}{'key_80_rtt'}, $services{'rdds'}{'key_43_upd'});
			$rdds_str_items_ref = get_rdds_str_itemids($tld, getopt('probe'), $services{'rdds'}{'key_43_ip'}, $services{'rdds'}{'key_80_ip'});
		}
		elsif ($service eq 'epp')
		{
			$epp_dbl_items_ref = get_epp_dbl_itemids($tld, getopt('probe'), $services{'epp'}{'key_rtt'});
			$epp_str_items_ref = get_epp_str_itemids($tld, getopt('probe'), $services{'epp'}{'key_ip'});
		}

		$incidents = get_incidents($avail_itemid, $service_from, $service_till);

		foreach (@$incidents)
		{
			my $eventid = $_->{'eventid'};
			my $event_start = $_->{'start'};
			my $event_end = $_->{'end'};
			my $false_positive = $_->{'false_positive'};

			my $start = $event_start;
			my $end = $event_end;

			if (defined($service_from) and $service_from > $event_start)
			{
				$start = $service_from;
			}

			if (defined($service_till))
			{
				if (not defined($event_end) or (defined($event_end) and $service_till < $event_end))
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
				" order by clock");

			my @test_results;

			my $status_up = 0;
			my $status_down = 0;

			foreach my $row_ref (@$rows_ref)
			{
				my $value = $row_ref->[0];
				my $clock = $row_ref->[1];

				my $result;

				$result->{'tld'} = $tld;
				$result->{'status'} = get_result_string($cfg_dns_statusmaps, $value);
				$result->{'clock'} = $clock;

				# We have the test resulting value (Up or Down) at "clock". Now we need to select the
				# time bounds (start/end) of all data points from all proxies.
				#
				#   +........................period (service delay)...........................+
				#   |                                                                         |
				# start                                 clock                                end
				#   |.....................................|...................................|
				#   0 seconds <--zero or more minutes--> 30                                  59
				#
				$result->{'start'} = $clock - $delay + RESULT_TIMESTAMP_SHIFT + 1; # we need to start at 0
				$result->{'end'} = $clock + RESULT_TIMESTAMP_SHIFT;

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
						wrn("unknown status: $value (expected UP (0) or DOWN (1))");
					}
				}

				push(@test_results, $result);
			}

			my $test_results_count = scalar(@test_results);

			if ($test_results_count == 0)
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
				if (ah_save_incident($ah_tld, $service, $eventid, $event_start, $event_end, $false_positive, $lastclock) != AH_SUCCESS)
				{
					fail("cannot save incident: ", ah_get_error());
				}
			}

			my $values_from = $test_results[0]->{'start'};
			my $values_till = $test_results[$test_results_count - 1]->{'end'};

			if ($service eq 'dns' or $service eq 'dnssec')
			{
				my $minns = $services{$service}{'minns'};

				my $values_ref = get_dns_test_values($dns_items_ref, $services{$service}{'valuemaps'}, $values_from, $values_till);

				# run through values from probes (ordered by clock)
				foreach my $probe (keys(%$values_ref))
				{
					my $nsips_ref = $values_ref->{$probe};

					dbg("probe:$probe");

					foreach my $nsip (keys(%$nsips_ref))
					{
						my $endvalues_ref = $nsips_ref->{$nsip};

						my ($ns, $ip) = split(',', $nsip);

						dbg("  ", scalar(keys(%$endvalues_ref)), " values for $nsip:") if (opt('debug'));

						my $test_result_index = 0;

						foreach my $clock (sort(keys(%$endvalues_ref))) # must be sorted by clock
						{
							if ($clock < $test_results[$test_result_index]->{'start'})
							{
								no_status_result($service, $avail_key, $probe, $clock, $nsip);
								next;
							}

							# move to corresponding test result
							$test_result_index++ while ($test_result_index < $test_results_count and $clock > $test_results[$test_result_index]->{'end'});

							if ($test_result_index == $test_results_count)
							{
								no_status_result($service, $avail_key, $probe, $clock, $nsip);
								next;
							}

							my $tr_ref = $test_results[$test_result_index];
							$tr_ref->{'probes'}->{$probe}->{'status'} = undef;	# the status is set later

							if (probe_offline_at($probe_times_ref, $probe, $clock) != 0)
							{
								$tr_ref->{'probes'}->{$probe}->{'status'} = PROBE_OFFLINE_STR;
							}
							else
							{
								push(@{$tr_ref->{'probes'}->{$probe}->{'details'}->{$ns}}, {'clock' => $clock, 'rtt' => $endvalues_ref->{$clock}, 'ip' => $ip});
							}
						}
					}
				}

				# add probes that are missing results
				foreach my $probe (keys(%$all_probes_ref))
				{
					foreach my $tr_ref (@test_results)
					{
						my $found = 0;

						my $probes_ref = $tr_ref->{'probes'};
						foreach my $tr_ref_probe (keys(%$probes_ref))
						{
							if ($tr_ref_probe eq $probe)
							{
								dbg("\"$tr_ref_probe\" found!");

								$found = 1;
								last;
							}
						}

						$probes_ref->{$probe}->{'status'} = PROBE_NORESULT_STR if ($found == 0);
					}
				}

				# get results from probes: number of working Name Servers
				my $itemids_ref = get_service_status_itemids($tld, $services{$service}{'key_status'});
				my $statuses_ref = get_probe_statuses($itemids_ref, $values_from, $values_till);

				foreach my $tr_ref (@test_results)
				{
					# set status
					my $tr_start = $tr_ref->{'start'};
					my $tr_end = $tr_ref->{'end'};

					delete($tr_ref->{'start'});
					delete($tr_ref->{'end'});

					my $probes_ref = $tr_ref->{'probes'};
					foreach my $probe (keys(%$probes_ref))
					{
						foreach my $status_ref (@{$statuses_ref->{$probe}})
						{
							next if ($status_ref->{'clock'} < $tr_start);
							last if ($status_ref->{'clock'} > $tr_end);

							if (not defined($probes_ref->{$probe}->{'status'}))
							{
								$probes_ref->{$probe}->{'status'} = ($status_ref->{'value'} >= $minns ? "Up" : "Down");
							}
						}
					}

					if (opt('dry-run'))
					{
						__prnt_json($tr_ref);
					}
					else
					{
						if (ah_save_incident_json($ah_tld, $service, $eventid, $event_start, encode_json($tr_ref), $tr_ref->{'clock'}) != AH_SUCCESS)
						{
							fail("cannot save incident: ", ah_get_error());
						}
					}
				}
			}
			elsif ($service eq 'rdds')
			{
				my $values_ref = get_rdds_test_values($rdds_dbl_items_ref, $rdds_str_items_ref, $services{$service}{'valuemaps'}, $values_from, $values_till);

				# run through values from probes (ordered by clock)
				foreach my $probe (keys(%$values_ref))
				{
					my $subservices_ref = $values_ref->{$probe};

					dbg("probe:$probe");

					foreach my $subservice (keys(%$subservices_ref))
					{
						my $test_result_index = 0;

						foreach my $endvalues_ref (@{$subservices_ref->{$subservice}})
						{
							my $clock = $endvalues_ref->{'clock'};

							if ($clock < $test_results[$test_result_index]->{'start'})
							{
								no_status_result($subservice, $avail_key, $probe, $clock);
								next;
							}

							# move to corresponding test result
							$test_result_index++ while ($test_result_index < $test_results_count and $clock > $test_results[$test_result_index]->{'end'});

							if ($test_result_index == $test_results_count)
							{
								no_status_result($subservice, $avail_key, $probe, $clock);
								next;
							}

							my $tr_ref = $test_results[$test_result_index];
							$tr_ref->{+JSON_RDDS_SUBSERVICE}->{$subservice}->{$probe}->{'status'} = undef;	# the status is set later

							if (probe_offline_at($probe_times_ref, $probe, $clock) != 0)
							{
								$tr_ref->{+JSON_RDDS_SUBSERVICE}->{$subservice}->{$probe}->{'status'} = PROBE_OFFLINE_STR;
							}
							else
							{
								push(@{$tr_ref->{+JSON_RDDS_SUBSERVICE}->{$subservice}->{$probe}->{'details'}}, $endvalues_ref);
							}
						}
					}
				}

				# add probes that are missing results
				foreach my $probe (keys(%$all_probes_ref))
				{
					foreach my $tr_ref (@test_results)
					{
						my $subservices_ref = $tr_ref->{+JSON_RDDS_SUBSERVICE};

						foreach my $subservice (keys(%$subservices_ref))
						{
							my $probes_ref = $subservices_ref->{$subservice};

							my $found = 0;

							foreach my $tr_ref_probe (keys(%$probes_ref))
							{
								if ($tr_ref_probe eq $probe)
								{
									$found = 1;
									last;
								}
							}

							$probes_ref->{$probe}->{'status'} = PROBE_NORESULT_STR if ($found == 0);
						}
					}
				}

				# get results from probes: working services (rdds43, rdds80)
				my $itemids_ref = get_service_status_itemids($tld, $services{$service}{'key_status'});
				my $statuses_ref = __get_probe_statuses($itemids_ref, $values_from, $values_till);

				foreach my $tr_ref (@test_results)
				{
					# set status
					my $tr_start = $tr_ref->{'start'};
					my $tr_end = $tr_ref->{'end'};

					delete($tr_ref->{'start'});
					delete($tr_ref->{'end'});

					my $subservices_ref = $tr_ref->{+JSON_RDDS_SUBSERVICE};

					foreach my $subservice (keys(%$subservices_ref))
					{
						my $probes_ref = $subservices_ref->{$subservice};

						foreach my $probe (keys(%$probes_ref))
						{
							foreach my $status_ref (@{$statuses_ref->{$probe}})
							{
								next if ($status_ref->{'clock'} < $tr_start);
								last if ($status_ref->{'clock'} > $tr_end);

								if (not defined($probes_ref->{$probe}->{'status'}))
								{
									my $service_only = ($subservice eq JSON_RDDS_43 ? 2 : 3); # 0 - down, 1 - up, 2 - only 43, 3 - only 80

									$probes_ref->{$probe}->{'status'} = (($status_ref->{'value'} == 1 or $status_ref->{'value'} == $service_only) ? "Up" : "Down");
								}
							}
						}
					}

					if (opt('dry-run'))
					{
						__prnt_json($tr_ref);
					}
					else
					{
						if (ah_save_incident_json($ah_tld, $service, $eventid, $event_start, encode_json($tr_ref), $tr_ref->{'clock'}) != AH_SUCCESS)
						{
							fail("cannot save incident: ", ah_get_error());
						}
					}
				}
			}
			elsif ($service eq 'epp')
			{
				dbg("EPP results calculation is not implemented yet");

				my $values_ref = get_epp_test_values($epp_dbl_items_ref, $epp_str_items_ref, $services{$service}{'valuemaps'}, $values_from, $values_till);

				foreach my $probe (keys(%$values_ref))
				{
					my $endvalues_ref = $values_ref->{$probe};

					my $test_result_index = 0;

					foreach my $clock (sort(keys(%$endvalues_ref))) # must be sorted by clock
					{
						if ($clock < $test_results[$test_result_index]->{'start'})
						{
							no_status_result($service, $avail_key, $probe, $clock);
							next;
						}

						# move to corresponding test result
						$test_result_index++ while ($test_result_index < $test_results_count and $clock > $test_results[$test_result_index]->{'end'});

						if ($test_result_index == $test_results_count)
						{
							no_status_result($service, $avail_key, $probe, $clock);
							next;
						}

						my $tr_ref = $test_results[$test_result_index];
						$tr_ref->{'probes'}->{$probe}->{'status'} = undef;	# the status is set later

						if (probe_offline_at($probe_times_ref, $probe, $clock) != 0)
						{
							$tr_ref->{'probes'}->{$probe}->{'status'} = PROBE_OFFLINE_STR;
						}
						else
						{
							$tr_ref->{'probes'}->{$probe}->{'details'}->{$clock} = $endvalues_ref->{$clock};
						}
					}
				}

				# add probes that are missing results
				foreach my $probe (keys(%$all_probes_ref))
				{
					foreach my $tr_ref (@test_results)
					{
						my $found = 0;

						my $probes_ref = $tr_ref->{'probes'};
						foreach my $tr_ref_probe (keys(%$probes_ref))
						{
							if ($tr_ref_probe eq $probe)
							{
								dbg("\"$tr_ref_probe\" found!");

								$found = 1;
								last;
							}
						}

						$probes_ref->{$probe}->{'status'} = PROBE_NORESULT_STR if ($found == 0);
					}
				}

				# get results from probes: EPP down (0) or up (1)
				my $itemids_ref = get_service_status_itemids($tld, $services{$service}{'key_status'});
                                my $statuses_ref = __get_probe_statuses($itemids_ref, $values_from, $values_till);

				foreach my $tr_ref (@test_results)
                                {
                                        # set status
                                        my $tr_start = $tr_ref->{'start'};
                                        my $tr_end = $tr_ref->{'end'};

                                        delete($tr_ref->{'start'});
                                        delete($tr_ref->{'end'});

                                        my $probes_ref = $tr_ref->{'probes'};

					foreach my $probe (keys(%$probes_ref))
					{
						foreach my $status_ref (@{$statuses_ref->{$probe}})
						{
							next if ($status_ref->{'clock'} < $tr_start);
							last if ($status_ref->{'clock'} > $tr_end);

							if (not defined($probes_ref->{$probe}->{'status'}))
							{
								$probes_ref->{$probe}->{'status'} = ($status_ref->{'value'} == 1 ? "Up" : "Down");
							}
						}
					}

					if (opt('dry-run'))
					{
						__prnt_json($tr_ref);
					}
					else
					{
						if (ah_save_incident_json($ah_tld, $service, $eventid, $event_start, encode_json($tr_ref), $tr_ref->{'clock'}) != AH_SUCCESS)
						{
							fail("cannot save incident: ", ah_get_error());
						}
					}
				}
			}
			else
			{
				fail("THIS SHOULD NEVER HAPPEN (unknown service \"$service\")");
			}
		}
	}
}
# unset TLD (for the logs)
$tld = undef;

if (defined($continue_file) and not opt('dry-run'))
{
	unless (write_file($continue_file, $till) == SUCCESS)
	{
		wrn("cannot update continue file \"$continue_file\": $!");
		next;
	}

	dbg("last update: ", ts_str($till));
}

unless (opt('dry-run') or opt('tld'))
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
	my $tr_ref = shift;

	if (opt('debug'))
	{
		dbg(JSON->new->utf8(1)->pretty(1)->encode($tr_ref), "-----------------------------------------------------------");
	}
	else
	{
		__prnt(ts_str($tr_ref->{'clock'}), " ", $tr_ref->{'status'});
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

		fail("cannot update false_positive status of event with ID $eventid") unless (ah_save_false_positive($tld, $service, $eventid, $event_clock, $false_positive, $clock) == AH_SUCCESS);
	}

	ah_save_audit($maxclock) unless ($maxclock == 0);
}

sub __validate_input
{
	if (opt('service'))
	{
		if (getopt('service') ne 'dns' and getopt('service') ne 'dnssec' and getopt('service') ne 'rdds' and getopt('service') ne 'epp')
		{
			print("Error: \"", getopt('service'), "\" - unknown service\n");
			usage();
		}
	}

	if (opt('tld') and opt('ignore-file'))
	{
		print("Error: options --tld and --ignore-file cannot be used together\n");
		usage();
	}

	if (opt('continue') and opt('from'))
        {
                print("Error: options --continue and --from cannot be used together\n");
                usage();
        }

	if (opt('probe'))
	{
		if (not opt('dry-run'))
		{
			print("Error: option --probe can only be used together with --dry-run\n");
			usage();
		}

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
		$key_condition = "key_='" . $services{$service}{'key_status'} . "'";
	}
	elsif ($service eq 'rdds')
	{
		$key_condition = "key_ like '" . $services{$service}{'key_status'} . "%'";
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
