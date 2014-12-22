#!/usr/bin/perl -w

use lib '/opt/zabbix/scripts';

use strict;
use RSM;
use RSMSLV;
use ApiHelper;
use JSON::XS;
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



#my $hashref = {'foo' => 1, 'bar' => 3, 'baz' => 13, 'subhash' => {'subhash1' => 121, 'subhash2' => 124}};
#my $json = encode_json($hashref);
#my $href = decode_json($json);
#print(Dumper($href));
#exit;



# [
#  {
#      'clock' => '1418908679',
#      'status' => 'Down',
#      'probes' =>
#      {
# 	 'Amsterdam' =>
# 	 {
# 	     'status' => 'Down',
# 	     'nss' =>
# 	     {
# 		 'ns1.barbadosdomain.net' =>
# 		     [
# 		      {
# 			  'ip' => '200.50.92.195',
# 			  'rtt' => '131',
# 			  'clock' => '1418908679'
# 		      },
# 		      {
# 			  'ip' => '200.50.92.196',
# 			  'rtt' => '134',
# 			  'clock' => '1418908679'
# 		      }
# 		     ],
# 		 'ns2.barbadosdomain.net' =>
# 		     [
# 		      {
# 			  'ip' => '200.50.92.196',
# 			  'rtt' => '-200, No reply from Name Server',
# 			  'clock' => '1418908679'
# 		      }
# 		     ]
# 	     }
# 	 },
# 	 'London' =>
# 	 {
# 	     'status' => 'Down',
# 	     'nss' =>
# 	     {
# 		 'ns1.barbadosdomain.net' =>
# 		     [
# 		      {
# 			  'ip' => '200.50.92.195',
# 			  'rtt' => '131',
# 			  'clock' => '1418908679'
# 		      },
# 		      {
# 			  'ip' => '200.50.92.197',
# 			  'rtt' => '-200, No reply from Name Server',
# 			  'clock' => '1418908679'
# 		      }
# 		     ]
# 	     }
# 	 }
#      }
#  },
#  {
#      'clock' => '1418908739',
#      'status' => 'Down',
#      'probes' =>
#      {
# 	 'Amsterdam' =>
# 	 {
# 	     'status' => 'Down',
# 	     'nss' =>
# 	     {
# 		 'ns1.barbadosdomain.net' =>
# 		     [
# 		      {
# 			  'ip' => '200.50.92.195',
# 			  'rtt' => '131',
# 			  'clock' => '1418908739'
# 		      },
# 		      {
# 			  'ip' => '200.50.92.196',
# 			  'rtt' => '134',
# 			  'clock' => '1418908739'
# 		      }
# 		     ],
# 		 'ns2.barbadosdomain.net' =>
# 		     [
# 		      {
# 			  'ip' => '200.50.92.196',
# 			  'rtt' => '-200, No reply from Name Server',
# 			  'clock' => '1418908739'
# 		      }
# 		     ]
# 	     }
# 	 },
# 	 'London' =>
# 	 {
# 	     'status' => 'Down',
# 	     'nss' =>
# 	     {
# 		 'ns1.barbadosdomain.net' =>
# 		     [
# 		      {
# 			  'ip' => '200.50.92.195',
# 			  'rtt' => '131',
# 			  'clock' => '1418908739'
# 		      },
# 		      {
# 			  'ip' => '200.50.92.197',
# 			  'rtt' => '-200, No reply from Name Server',
# 			  'clock' => '1418908739'
# 		      }
# 		     ]
# 	     }
# 	 }
#      }
#  }
# ];

my $dns_interval = get_macro_dns_udp_delay();

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

	if (ah_save_service_availability($tld, $service, $downtime) != AH_SUCCESS)
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

	if (ah_save_alarmed($tld, $service, $alarmed_status) != AH_SUCCESS)
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

	    my $start = $from if (defined($from) and $_->{'start'} < $from);
	    my $end = $till if (defined($till) and not defined($end));

	    if (ah_save_incident($tld, $OPTS{'service'}, $eventid, $start, $end, $false_positive) != AH_SUCCESS)
	    {
		fail("cannot save incident: ", ah_get_error());
	    }

	    if ($service eq 'dns')
	    {
		my $interval = $dns_interval;

		# get results within incidents
		my $rows_ref = db_select(
		    "select value,clock".
		    " from history_uint".
		    " where itemid=$itemid".
		    	" and ".sql_time_condition($start, $end).
		    " order by clock");

		my @test_results;

		foreach my $row_ref (@$rows_ref)
		{
		    my $value = $row_ref->[0];
		    my $clock = $row_ref->[1];

		    my $result;

		    $result->{'status'} = $value;
		    $result->{'clock'} = $clock;

		    # time bounds for results from proxies
		    $result->{'start'} = $clock - $interval + RESULT_TIMESTAMP_SHIFT + 1; # we need to start at 0
		    $result->{'end'} = $clock + RESULT_TIMESTAMP_SHIFT;

		    push(@test_results, $result);
		}

		fail("no results found within incident: eventid:$eventid start:$start") if (scalar(@test_results) == 0);

		my $values_from = $test_results[0]->{'start'};
		my $values_till = $test_results[scalar(@test_results) - 1]->{'end'};

		my $key = 'rsm.dns.udp.rtt[{$RSM.TLD},';

		# TODO: move out of the cycle
		my $nsips_ref = get_nsips($tld, $key, 1); # templated

		# TODO: move out of the cycle
		my $nsip_items_ref = __get_nsip_items($nsips_ref, $key, $tld);

		my $values_ref = __get_values($nsip_items_ref, $values_from, $values_till);

		my $test_results_count = scalar(@test_results);

		# run through values from probes (ordered by clock)
		foreach my $probe (keys(%$values_ref))
		{
		    my $nsips_ref = $values_ref->{$probe};

		    dbg("probe:$probe");

		    foreach my $nsip (keys(%$nsips_ref))
		    {
			my $nsip_ref = $nsips_ref->{$nsip};

			my ($ns, $ip) = split(',', $nsip);

			my $nsip_results_ref = $nsip_ref->{'results'};

			my $total = scalar(@$nsip_results_ref);

			dbg("  $nsip: found $total:");

			my $test_result_index = 0;

			foreach my $result_ref (@$nsip_results_ref)
			{
			    my $value = $result_ref->{'value'};
			    my $clock = $result_ref->{'clock'};

			    dbg("test ", $test_result_index + 1, " start:", $test_results[$test_result_index]->{'start'}, " clock:", $test_results[$test_result_index]->{'clock'}, " end:", $test_results[$test_result_index]->{'end'});

			    fail("no status in the database related to value (probe:$probe service:$service clock:$clock value:$value)") if ($clock < $test_results[$test_result_index]->{'start'});

			    # move to corresponding test result
			    $test_result_index++ while ($test_result_index < $test_results_count and $clock > $test_results[$test_result_index]->{'end'});

			    fail("no status in the database related to value (probe:$probe service:$service clock:$clock value:$value)") if ($test_result_index == $test_results_count);

			    my $r_ref = $test_results[$test_result_index];

			    my $r_status = $r_ref->{'status'};
			    my $r_clock = $r_ref->{'clock'};
			    my $r_start = $r_ref->{'start'};
			    my $r_end = $r_ref->{'end'};

			    $r_ref->{'probes'} = {} unless (exists($r_ref->{'probes'}));
			    $r_ref->{'probes'}->{$probe} = {} unless (exists($r_ref->{'probes'}->{$probe}));
			    $r_ref->{'probes'}->{$probe}->{'status'} = ':TODO:' unless (exists($r_ref->{'probes'}->{$probe}->{'nss'}->{'status'}));
			    $r_ref->{'probes'}->{$probe}->{'nss'} = {} unless (exists($r_ref->{'probes'}->{$probe}->{'nss'}));
			    $r_ref->{'probes'}->{$probe}->{'nss'}->{$ns} = [] unless (exists($r_ref->{'probes'}->{$probe}->{'nss'}->{$ns}));

			    my $ns_ref = $r_ref->{'probes'}->{$probe}->{'nss'}->{$ns};

			    push(@$ns_ref, {'ip' => $ip, 'rtt' => $value, 'clock' => $clock});
			}
		    }
		}

		foreach my $r_ref (@test_results)
		{
		    delete($r_ref->{'start'});
		    delete($r_ref->{'end'});

		    my $json = encode_json($r_ref);

		    if (ah_save_incident_json($tld, $OPTS{'service'}, $eventid, $start, $json, $r_ref->{'clock'}) != AH_SUCCESS)
		    {
			fail("cannot save incident: ", ah_get_error());
		    }

		    dbg(JSON->new->utf8(1)->pretty(1)->encode($r_ref), "-----------------------------------------------------------\n") if (defined($OPTS{'debug'}));
		}
	    }
	}
    }
}

# unset TLD (for the logs)
$tld = undef;

slv_exit(SUCCESS);

# values are organized like this:
# {
#     'Amsterdam' => {
#         'ns1,192.0.34.201' => {
#             'values' => [
#                 '-204',
#                 '-204',
#                 '124',
#                 ...
sub __get_values
{
    my $nsip_items_ref = shift;
    my $start = shift;
    my $end = shift;

    my %result;

    # generate list if itemids
    my $itemids_str = '';
    foreach my $host (keys(%$nsip_items_ref))
    {
	my $itemids_ref = $nsip_items_ref->{$host};

	foreach my $itemid (keys(%$itemids_ref))
	{
	    $itemids_str .= ',' unless ($itemids_str eq '');
	    $itemids_str .= $itemid;
	}
    }

    if ($itemids_str ne '')
    {
	my $rows_ref = db_select("select itemid,value,clock from history where itemid in ($itemids_str) and " . sql_time_condition($start, $end). " order by clock");

	foreach my $row_ref (@$rows_ref)
	{
	    my $itemid = $row_ref->[0];
	    my $value = $row_ref->[1];
	    my $clock = $row_ref->[2];

	    my ($nsip, $host);
	    my $last = 0;

	    foreach my $hid (keys(%$nsip_items_ref))
	    {
		my $itemids_ref = $nsip_items_ref->{$hid};

		foreach my $i (keys(%$itemids_ref))
		{
		    if ($i == $itemid)
		    {
			$nsip = $nsip_items_ref->{$hid}{$i};
			$host = $hid;
			$last = 1;
			last;
		    }
		}
		last if ($last == 1);
	    }

	    fail("internal error: Name Server,IP pair of item $itemid not found") unless (defined($nsip));

	    $result{$host} = {} unless (exists($result{$host}));

	    if (exists($result{$host}{$nsip}))
	    {
		push(@{$result{$host}{$nsip}->{'results'}}, {'value' => $value, 'clock' => $clock});
	    }
	    else
	    {
		$result{$host}{$nsip} = {'results' => [{'value' => $value, 'clock' => $clock}]};
	    }
	}
    }

    return \%result;
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
sub __get_nsip_items
{
    my $nsips_ref = shift; # array reference of NS,IP pairs
    my $cfg_key_in = shift;
    my $tld = shift;

    my @keys;
    push(@keys, "'" . $cfg_key_in . $_ . "]'") foreach (@$nsips_ref);

    my $keys_str = join(',', @keys);

    my $rows_ref = db_select(
	"select h.host,i.itemid,i.key_ ".
	"from items i,hosts h ".
	"where i.hostid=h.hostid".
		" and h.host like '$tld %'".
		" and i.templateid is not null".
		" and i.key_ in ($keys_str)");

    my %result;

    my $tld_length = length($tld) + 1; # skip white space
    foreach my $row_ref (@$rows_ref)
    {
	my $host = $row_ref->[0];
	my $itemid = $row_ref->[1];
	my $key = $row_ref->[2];

	# remove TLD from host name to get just the Probe name
	my $probe = substr($host, $tld_length);

	$result{$probe}{$itemid} = get_ns_from_key($key);
    }

    fail("cannot find items ($keys_str) at host ($tld *)") if (scalar(keys(%result)) == 0);

    return \%result;
}

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
