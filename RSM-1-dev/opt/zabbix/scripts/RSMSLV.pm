package RSMSLV;

use strict;
use warnings;

use DBI;
use Getopt::Long;
use Pod::Usage;
use Exporter qw(import);
use Zabbix;
use Sender;
use Alerts;
use File::Pid;
use POSIX qw(floor);
use Sys::Syslog;
use Data::Dumper;
use Time::HiRes qw(time);
use Fcntl qw(:flock);
use TLD_constants qw(:api);

use constant SUCCESS	=> 0;
use constant E_FAIL	=> -1;

use constant DOWN		=> 0;
use constant UP			=> 1;
use constant UP_INCONCLUSIVE	=> 2;

use constant ONLINE	=> 1;
use constant OFFLINE	=> 0;

use constant SLV_UNAVAILABILITY_LIMIT	=> 49; # NB! must be in sync with frontend

use constant MAX_SERVICE_ERROR	=> -200; # -200, -201 ...
use constant MIN_LOGIN_ERROR	=> -205;
use constant MAX_LOGIN_ERROR	=> -203;
use constant MIN_INFO_ERROR	=> -211;
use constant MAX_INFO_ERROR	=> -209;

use constant EVENT_OBJECT_TRIGGER	=> 0;
use constant EVENT_SOURCE_TRIGGERS	=> 0;
use constant TRIGGER_VALUE_FALSE	=> 0;
use constant TRIGGER_VALUE_TRUE		=> 1;
use constant INCIDENT_FALSE_POSITIVE	=> 1; # NB! must be in sync with frontend
use constant SENDER_BATCH_COUNT		=> 250;
use constant PROBE_LASTACCESS_ITEM	=> 'zabbix[proxy,{$RSM.PROXY_NAME},lastaccess]';
use constant PROBE_GROUP_NAME		=> 'Probes';
use constant PROBE_KEY_MANUAL		=> 'rsm.probe.status[manual]';
use constant PROBE_KEY_AUTOMATIC	=> 'rsm.probe.status[automatic,%]';	# match all in SQL
use constant PROBE_KEY_ONLINE		=> 'rsm.probe.online';

# In order to do the calculation we should wait till all the results
# are available on the server (from proxies). We shift back 2 minutes
# in case of "availability" and 3 minutes in case of "rolling week"
# calculations.
# NB! These numbers must be in sync with Frontend (details page)!
use constant PROBE_ONLINE_SHIFT		=> 120;	# seconds (must be divisible by 60 without remainder), wait for probe status data
use constant AVAIL_SHIFT_BACK		=> 180;	# seconds (must be divisible by 60 without remainder), wait for data from probes
use constant ROLLWEEK_SHIFT_BACK	=> 240;	# seconds (must be divisible by 60 without remainder), wait for availability data
use constant VALUE_TS_SHIFT		=> 29;	# seconds (shift back from upper time bound, should get :30 seconds)

use constant PROBE_ONLINE_STR	=> 'Online';
use constant PROBE_OFFLINE_STR	=> 'Offline';
use constant PROBE_NORESULT_STR	=> 'No result';

use constant JSON_INTERFACE_DNS		=> 'DNS';
use constant JSON_INTERFACE_DNSSEC	=> 'DNSSEC';
use constant JSON_INTERFACE_RDDS43	=> 'RDDS43';
use constant JSON_INTERFACE_RDDS80	=> 'RDDS80';

use constant JSON_TAG_TARGET_IP		=> 'targetIP';
use constant JSON_TAG_CLOCK		=> 'clock';
use constant JSON_TAG_RTT		=> 'rtt';
use constant JSON_TAG_UPD		=> 'upd';
use constant JSON_TAG_DESCRIPTION	=> 'description';

use constant SEC_PER_WEEK	=> 604800;

our ($result, $dbh, $tld);

my $start_time;
my $sql_time = 0.0;
my $sql_count = 0;
my $lock_time = 0.0;
my $lock_wait_time = 0.0;
my $lock_count = 0;
my $lock_tmp;

my %OPTS;	# command-line options

our @EXPORT = qw($result $dbh $tld
		SUCCESS E_FAIL UP UP_INCONCLUSIVE DOWN SLV_UNAVAILABILITY_LIMIT MIN_LOGIN_ERROR MAX_LOGIN_ERROR
		MIN_INFO_ERROR MAX_INFO_ERROR PROBE_ONLINE_STR PROBE_OFFLINE_STR PROBE_NORESULT_STR SEC_PER_WEEK
		PROBE_ONLINE_SHIFT AVAIL_SHIFT_BACK JSON_INTERFACE_DNS JSON_INTERFACE_DNSSEC JSON_INTERFACE_RDDS43
		JSON_INTERFACE_RDDS80
		JSON_TAG_TARGET_IP JSON_TAG_CLOCK JSON_TAG_RTT JSON_TAG_UPD JSON_TAG_DESCRIPTION
		get_macro_minns get_macro_dns_probe_online get_macro_rdds_probe_online get_macro_dns_rollweek_sla
		get_macro_rdds_rollweek_sla get_macro_dns_udp_rtt_high get_macro_dns_udp_rtt_low
		get_macro_dns_tcp_rtt_low get_macro_rdds_rtt_low get_macro_dns_udp_delay get_macro_dns_tcp_delay
		get_macro_rdds_delay get_macro_epp_delay get_macro_epp_probe_online get_macro_epp_rollweek_sla
		get_macro_dns_update_time get_macro_rdds_update_time get_items_by_hostids get_tld_items get_hostid
		get_macro_epp_rtt_low get_macro_probe_avail_limit get_item_data get_itemid_by_key get_itemid_by_host
		get_itemid_by_hostid get_itemid_like_by_hostid get_itemids_by_host_and_keypart get_lastclock get_tlds
		get_probe_macros get_ipv_probes
		get_probes get_templated_nsips get_nsips get_all_items get_nsip_items tld_exists tld_service_enabled
		db_connect db_disconnect db_select db_select_binds
		db_exec set_slv_config get_cycle_bounds get_rollweek_bounds get_prev_month_bounds get_month_bounds
		get_month_from get_num_cycles minutes_last_month max_avail_time get_probe_times get_probe_times2
		get_probe_availabilities
		probe_offline_at probes2tldhostids get_probe_online_key_itemid
		init_values push_value send_values get_nsip_from_key get_ip_from_nsip is_service_error
		process_slv_monthly process_slv_ns_monthly
		process_slv_avail process_slv_ns_avail process_slv_downtime get_results uint_value_exists
		dbl_value_exists get_dns_itemids get_rdds_dbl_itemids get_rdds_str_itemids get_epp_dbl_itemids
		get_epp_str_itemids get_dns_test_values get_rdds_test_values get_epp_test_values no_cycle_result
		get_service_status_itemids get_probe_results get_ip_version
		sql_time_condition get_incidents get_incidents2 get_downtime get_downtime_prepare get_downtime_execute
		avail_result_msg
		get_current_value get_itemids_by_hostids get_valuemaps get_statusmaps get_detailed_result
		get_result_string get_tld_by_trigger truncate_from truncate_till get_value_ts alerts_enabled
		is_from_valid is_till_valid get_test_start_time avail_up_down
		get_real_services_period dbg info wrn fail format_stats_time friendly_delay slv_exit slv_stats_reset
		slv_lock slv_unlock
		exit_if_running trim parse_opts parse_avail_opts parse_rollweek_opts opt getopt setopt optkeys ts_str
		ts_full selected_period write_file
		cycle_start cycle_end get_default_period rsm_slv_error get_readable_tld usage);

# configuration, set in set_slv_config()
my $config = undef;

use constant PID_DIR => '/tmp';

use constant ROOT_ZONE_READABLE => 'zz--root';	# map root zone (.) to something human readable

my $_sender_values;	# used to send values to Zabbix server

my $POD2USAGE_FILE;	# usage message file

my ($_global_sql, $_lock_fh);

my $__rsm_slv_error = "";

use constant _LOCK_FILE => '/tmp/rsm.slv.lock';

sub get_macro_minns
{
	return __get_macro('{$RSM.DNS.AVAIL.MINNS}');
}

sub get_macro_dns_probe_online
{
	return __get_macro('{$RSM.DNS.PROBE.ONLINE}');
}

sub get_macro_rdds_probe_online
{
	return __get_macro('{$RSM.RDDS.PROBE.ONLINE}');
}

sub get_macro_dns_rollweek_sla
{
	return __get_macro('{$RSM.DNS.ROLLWEEK.SLA}');
}

sub get_macro_rdds_rollweek_sla
{
	return __get_macro('{$RSM.RDDS.ROLLWEEK.SLA}');
}

sub get_macro_dns_udp_rtt_high
{
	return __get_macro('{$RSM.DNS.UDP.RTT.HIGH}');
}

sub get_macro_dns_udp_rtt_low
{
	return __get_macro('{$RSM.DNS.UDP.RTT.LOW}');
}

sub get_macro_dns_tcp_rtt_low
{
	return __get_macro('{$RSM.DNS.TCP.RTT.LOW}');
}

sub get_macro_rdds_rtt_low
{
	return __get_macro('{$RSM.RDDS.RTT.LOW}');
}

sub __get_macro_delay
{
	my $m_name = shift;
	my $value_time = (shift or int(time()) - AVAIL_SHIFT_BACK);

	my $value = __get_rsm_configvalue($m_name, $value_time);

	return $value if ($value);

	return __get_macro('{$' . $m_name . '}');
}

sub get_macro_dns_udp_delay
{
	return __get_macro_delay('RSM.DNS.UDP.DELAY', shift);
}

sub get_macro_dns_tcp_delay
{
	return __get_macro_delay('RSM.DNS.TCP.DELAY', shift);
}

sub get_macro_rdds_delay
{
	return __get_macro_delay('RSM.RDDS.DELAY', shift);
}

sub get_macro_epp_delay
{
	return __get_macro_delay('RSM.EPP.DELAY', shift);
}

sub get_macro_dns_update_time
{
	return __get_macro('{$RSM.DNS.UPDATE.TIME}');
}

sub get_macro_rdds_update_time
{
	return __get_macro('{$RSM.RDDS.UPDATE.TIME}');
}

sub get_macro_epp_probe_online
{
	return __get_macro('{$RSM.EPP.PROBE.ONLINE}');
}

sub get_macro_epp_rollweek_sla
{
	return __get_macro('{$RSM.EPP.ROLLWEEK.SLA}');
}

sub get_macro_epp_rtt_low
{
	return __get_macro('{$RSM.EPP.'.uc(shift).'.RTT.LOW}');
}

sub get_macro_probe_avail_limit
{
	return __get_macro('{$RSM.PROBE.AVAIL.LIMIT}');
}

sub get_item_data
{
	my $host = shift;
	my $cfg_key_in = shift;
	my $cfg_key_out = shift;

	my $sql;

	if ("[" eq substr($cfg_key_out, -1))
	{
		$sql =
			"select i.key_,i.itemid,i.lastclock".
			" from items i,hosts h".
			" where i.hostid=h.hostid".
				" and i.status<>".ITEM_STATUS_DISABLED.
				" and h.host='$host'".
				" and (i.key_='$cfg_key_in' or i.key_ like '$cfg_key_out%')";
	}
	else
	{
		$sql =
			"select i.key_,i.itemid,i.lastclock".
			" from items i,hosts h".
			" where i.hostid=h.hostid".
				" and i.status<>".ITEM_STATUS_DISABLED.
				" and h.host='$host'".
				" and i.key_ in ('$cfg_key_in','$cfg_key_out')";
	}

	$sql .= " order by i.key_";

	my $rows_ref = db_select($sql);

	my $rows = scalar(@$rows_ref);

	fail("cannot find items ($cfg_key_in and $cfg_key_out) at host ($host)") if ($rows < 2);

	my $itemid_in = undef;
	my $itemid_out = undef;
	my $lastclock = undef;

	foreach my $row_ref (@$rows_ref)
	{
		if ($row_ref->[0] eq $cfg_key_in)
		{
			$itemid_in = $row_ref->[1];
		}
		else
		{
			$itemid_out = $row_ref->[1];
			$lastclock = $row_ref->[2] ? $row_ref->[2] : 0;
		}

		last if (defined($itemid_in) and defined($itemid_out));
	}

	fail("cannot find itemid ($cfg_key_in and $cfg_key_out) at host ($host)")
		unless (defined($itemid_in) and defined($itemid_out));

	return ($itemid_in, $itemid_out, $lastclock);
}

sub get_itemid_by_key
{
	my $key = shift;

	my $errbuf;

	my $itemid = __get_itemid_by_sql(
		"select itemid".
		" from items".
		" where status<>".ITEM_STATUS_DISABLED.
			" and key_='$key'", \$errbuf);

	if (!$itemid)
	{
		$__rsm_slv_error = "cannot get itemid of \"$key\": $errbuf";
	}

	return $itemid;
}

sub get_itemid_by_host
{
	my $host = shift;
	my $key = shift;

	my $errbuf;

	my $sql =
		"select i.itemid".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and i.status<>".ITEM_STATUS_DISABLED.
	    		" and h.host='$host'".
			" and i.key_='$key'";

	return __get_itemid_by_sql($sql);
}

sub get_itemid_by_hostid
{
	my $hostid = shift;
	my $key = shift;

	my $errbuf;

	my $itemid = __get_itemid_by_sql(
		"select itemid".
		" from items".
		" where hostid=$hostid".
			" and status<>".ITEM_STATUS_DISABLED.
			" and key_='$key'", \$errbuf);

	if (!$itemid)
	{
		$__rsm_slv_error = "cannot get itemid of \"$key\" at hostid $hostid: $errbuf";
	}

	return $itemid;
}

sub get_itemid_like_by_hostid
{
	my $hostid = shift;
	my $key = shift;

	my $errbuf;

	my $itemid = __get_itemid_by_sql(
		"select itemid".
		" from items".
		" where hostid=$hostid".
			" and status<>".ITEM_STATUS_DISABLED.
			" and key_ like '$key'", \$errbuf);

	if (!$itemid)
	{
		$__rsm_slv_error = "cannot get itemid of item like \"$key\" at hostid $hostid: $errbuf";
	}

	return $itemid;
}

sub __get_itemid_by_sql
{
	my $sql = shift;
	my $errbuf = shift;

	my $rows_ref = db_select($sql);

	if (scalar(@$rows_ref) == 0)
	{
		$$errbuf = "item not found (sql was [$sql])";
		return;
	}

	if (scalar(@$rows_ref) > 1)
	{
		$$errbuf = "more than one item found (sql was [$sql])";
		return;
	}

        return $rows_ref->[0]->[0];
}

sub get_itemids_by_host_and_keypart
{
	my $host = shift;
	my $key_part = shift;

	my $rows_ref = db_select(
		"select i.itemid,i.key_".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and i.status<>".ITEM_STATUS_DISABLED.
	    		" and h.host='$host'".
			" and i.key_ like '$key_part%'");

	fail("cannot find items ($key_part%) at host ($host)") if (scalar(@$rows_ref) == 0);

	my %result;

	foreach my $row_ref (@$rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $key = $row_ref->[1];

		my $nsip = get_nsip_from_key($key);

		$result{$nsip} = $itemid;
	}

	return \%result;
}

# returns:
# E_FAIL  - if item was not found
# SUCCESS - otherwise
sub get_lastclock
{
	my $host = shift;
	my $key = shift;
	my $result_ptr = shift;

	my $sql;

	if ("[" eq substr($key, -1))
	{
		$sql =
			"select i.itemid,i.lastclock,i.lastvalue".
			" from items i,hosts h".
			" where i.hostid=h.hostid".
				" and i.status<>".ITEM_STATUS_DISABLED.
				" and h.host='$host'".
				" and i.key_ like '$key%'".
			" limit 1";
	}
	else
	{
		$sql =
			"select i.itemid,i.lastclock,i.lastvalue".
			" from items i,hosts h".
			" where i.hostid=h.hostid".
				" and i.status<>".ITEM_STATUS_DISABLED.
				" and h.host='$host'".
				" and i.key_='$key'";
	}

	my $rows_ref = db_select($sql);

	return E_FAIL if (scalar(@$rows_ref) == 0);

	$$result_ptr->{'itemid'} = $rows_ref->[0]->[0];

	if (defined($rows_ref->[0]->[0]) && $rows_ref->[0]->[0] > 0)
	{
		$$result_ptr->{'lastclock'} = $rows_ref->[0]->[1];
		$$result_ptr->{'lastvalue'} = $rows_ref->[0]->[2];
	}

	return SUCCESS;
}

sub get_tlds
{
	my $service = shift;	# in case of 2 services one of which is 'DNS' it has to be specified first
	my $service2 = shift;	# if 2 services have to be enabled, e. g. RDDS and EPP

	$service = defined($service) ? uc($service) : 'DNS';
	$service2 = uc($service2) if ($service2);

	my $rows_ref;

	if (!$service2)
	{
		if ($service eq 'DNS')
		{
			$rows_ref = db_select(
				"select h.host".
				" from hosts h,hosts_groups hg,groups g".
				" where h.hostid=hg.hostid".
					" and hg.groupid=g.groupid".
					" and g.name='TLDs'".
					" and h.status=0");
		}
		else
		{
			$rows_ref = db_select(
				"select h.host".
				" from hosts h,hosts_groups hg,groups g,hosts h2,hostmacro hm".
				" where h.hostid=hg.hostid".
					" and hg.groupid=g.groupid".
					" and h2.name=concat('Template ', h.host)".
					" and g.name='TLDs'".
					" and h2.hostid=hm.hostid".
					" and hm.macro='{\$RSM.TLD.$service.ENABLED}'".
					" and hm.value!=0".
					" and h.status=0");
		}
	}
	else
	{
		if ($service eq 'DNS')
		{
			$rows_ref = db_select(
				"select h.host,h2.hostid".
				" from hosts h,hosts_groups hg,groups g,hosts h2,hostmacro hm".
				" where h.hostid=hg.hostid".
					" and hg.groupid=g.groupid".
					" and h2.name=concat('Template ', h.host)".
					" and g.name='TLDs'".
					" and h2.hostid=hm.hostid".
					" and hm.macro='{\$RSM.TLD.$service2.ENABLED}'".
					" and hm.value!=0".
					" and h.status=0");
		}
		else
		{
			my $rows_ref2 = db_select(
				"select h.host,h2.hostid".
				" from hosts h,hosts_groups hg,groups g,hosts h2,hostmacro hm".
				" where h.hostid=hg.hostid".
					" and hg.groupid=g.groupid".
					" and h2.name=concat('Template ', h.host)".
					" and g.name='TLDs'".
					" and h2.hostid=hm.hostid".
					" and hm.macro='{\$RSM.TLD.$service.ENABLED}'".
					" and hm.value!=0".
					" and h.status=0");

			my $idx = 0;
			foreach my $row_ref (@{$rows_ref2})
			{
				next if (__get_host_macro($row_ref->[1], "{\$RSM.TLD.$service2.ENABLED}") == 0);

				$rows_ref->[$idx++]->[0] = $row_ref->[0];
			}
		}
	}

	my @tlds;
	foreach my $row_ref (sort {$a->[0] cmp $b->[0]} (@$rows_ref))
	{
		push(@tlds, $row_ref->[0]);
	}

	return \@tlds;
}

# Returns pointer to hash 'proxy' => 'macro' => 'value', e. g.
# {
#   Amsterdam =>
#   {
#     {RSM.IP4.ENABLED} => 1,
#     ...
#   }
# }
sub get_probe_macros
{
	my $rows_ref = db_select(
		"select host".
		" from hosts".
		" where status=".HOST_STATUS_PROXY_PASSIVE);

	my $result;

	foreach my $row_ref (@$rows_ref)
	{
		my $host = $row_ref->[0];

		my $rows_ref2 = db_select(
			"select hm.macro,hm.value".
			" from hosts h,hostmacro hm".
			" where h.hostid=hm.hostid".
				" and h.host='Template $host'");

		foreach my $row_ref2 (@$rows_ref2)
		{
			$result->{$host}->{$row_ref2->[0]} = $row_ref2->[1];
		}
	}

	return $result;
}

sub get_ipv_probes
{
	my $result = get_probe_macros();

	my $probes_ipv4 = 0;
	my $probes_ipv6 = 0;

	foreach my $probe (keys(%{$result}))
	{
		my $got_values = 0;

		foreach my $macro (keys(%{$result->{$probe}}))
		{
			if ($macro eq '{$RSM.IP4.ENABLED}')
			{
				$probes_ipv4++ if ($result->{$probe}->{$macro} eq '1');

				$got_values++;
			}
			elsif ($macro eq '{$RSM.IP6.ENABLED}')
			{
				$probes_ipv6++ if ($result->{$probe}->{$macro} eq '1');

				$got_values++;
			}

			last if ($got_values == 2);
		}
	}

	return ($probes_ipv4, $probes_ipv6);
}

# Returns a reference to hash of all probes (host => hostid).
sub get_probes
{
	my $service = shift;
	my $name = shift;

	$service = defined($service) ? uc($service) : 'DNS';

	my $name_cond = "";

	$name_cond = " and h.host='$name'" if ($name);

	my $rows_ref = db_select(
		"select h.host,h.hostid".
		" from hosts h, hosts_groups hg, groups g".
		" where h.hostid=hg.hostid".
			" and hg.groupid=g.groupid".
			$name_cond.
			" and g.name='".PROBE_GROUP_NAME."'");

	my $result;
	foreach my $row_ref (@$rows_ref)
	{
		my $host = $row_ref->[0];
		my $hostid = $row_ref->[1];

		if ($service ne 'DNS')
		{
			$rows_ref = db_select(
				"select hm.value".
				" from hosts h,hostmacro hm".
				" where h.hostid=hm.hostid".
					" and h.host='Template $host'".
					" and hm.macro='{\$RSM.$service.ENABLED}'");

			next if (scalar(@$rows_ref) != 0 and $rows_ref->[0]->[0] == 0);
		}

		$result->{$host} = $hostid;
	}

	return $result;
}

# get array of key nameservers ('i.ns.se,130.239.5.114', ...)
sub get_nsips
{
	my $host = shift;
	my $key = shift;

	my $rows_ref = db_select(
		"select key_".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and i.status<>".ITEM_STATUS_DISABLED.
			" and h.host='$host'".
			" and i.key_ like '$key%'");

	my @nss;
	foreach my $row_ref (@$rows_ref)
	{
		push(@nss, get_nsip_from_key($row_ref->[0]));
	}

	fail("cannot find items ($key*) at host ($host)") if (scalar(@nss) == 0);

	return \@nss;
}

sub get_templated_nsips
{
	my $host = shift;
	my $key = shift;

	return get_nsips("Template $host", $key);
}

#
# return itemids grouped by hosts:
#
# {
#   hostid1 => { itemid1 => '' },
#   hostid2 => { itemid2 => '' },
#   ...
# }
#
sub get_all_items
{
	my $key = shift;
	my $tld = shift;

	my $sql =
		"select h.hostid,i.itemid".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and i.status<>".ITEM_STATUS_DISABLED.
			" and i.templateid is not null".
			" and i.key_='$key'";
	$sql .=		" and h.host like '$tld %'" if (defined($tld));

	my $rows_ref = db_select($sql);

	my %result;

	foreach my $row_ref (@$rows_ref)
	{
		$result{$row_ref->[0]}{$row_ref->[1]} = '';
	}

	if (scalar(keys(%result)) == 0)
	{
		if (defined($tld))
		{
			fail("no items matching '$key' found at host '$tld %'");
		}
		else
		{
			fail("no items matching '$key' found in the database");
		}
	}

	return \%result;
}

# return itemids grouped by hosts:
#
# {
#    'probe hostid1' => {
#         'itemid1' => 'ns2,2620:0:2d0:270::1:201',
#         'itemid2' => 'ns1,192.0.34.201'
#    },
#    'probe hostid2' => {
#         'itemid3' => 'ns2,2620:0:2d0:270::1:201',
#         'itemid4' => 'ns1,192.0.34.201'
#    }
# }
sub get_nsip_items
{
	my $nsips_ref = shift;	# array reference of NS,IP pairs
	my $cfg_key_in = shift;
	my $tld = shift;

	my @keys;
	push(@keys, "'" . $cfg_key_in . $_ . "]'") foreach (@$nsips_ref);

	my $keys_str = join(',', @keys);

	my $rows_ref = db_select(
		"select h.hostid,i.itemid,i.key_".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and i.status<>".ITEM_STATUS_DISABLED.
			" and h.host like '$tld %'".
			" and i.templateid is not null".
			" and i.key_ in ($keys_str)");

	my %result;
	foreach my $row_ref (@$rows_ref)
	{
		$result{$row_ref->[0]}{$row_ref->[1]} = get_nsip_from_key($row_ref->[2]);
	}

	fail("cannot find items ($keys_str) at host ($tld *)") if (scalar(keys(%result)) == 0);

	return \%result;
}

sub get_items_by_hostids
{
	my $hostids_ref = shift;
	my $cfg_key = shift;
	my $complete = shift;

	my $hostids = join(',', @$hostids_ref);

	my $rows_ref;

	my $pre_sql =
		"select itemid,hostid".
		" from items".
		" where hostid in ($hostids)".
			" and status<>".ITEM_STATUS_DISABLED;

	if ($complete)
	{
		$rows_ref = db_select($pre_sql . " and key_='$cfg_key'");
	}
	else
	{
		$rows_ref = db_select($pre_sql . " and key_ like '$cfg_key%'");
	}

	my @items;
	foreach my $row_ref (@$rows_ref)
	{
		my %hash;
		$hash{'itemid'} = $row_ref->[0];
		$hash{'hostid'} = $row_ref->[1];
		push(@items, \%hash);
	}

	fail("cannot find items ($cfg_key", ($complete ? '' : '*'), ") at hostids ($hostids)") if (scalar(@items) == 0);

	return \@items;
}

sub get_tld_items
{
	my $tld = shift;
	my $cfg_key = shift;

	my $rows_ref = db_select(
		"select i.itemid,i.key_".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and i.status<>".ITEM_STATUS_DISABLED.
			" and h.host='$tld'".
			" and i.key_ like '$cfg_key%'");

	my @items;
	foreach my $row_ref (@$rows_ref)
	{
		push(@items, $row_ref);
	}

	fail("cannot find items ($cfg_key*) at host ($tld)") if (scalar(@items) == 0);

	return \@items;
}

sub get_hostid
{
	my $host = shift;

	my $rows_ref = db_select("select hostid from hosts where host='$host'");

	fail("cannot get host ID of host '$host'") unless (scalar(@$rows_ref) == 1);

	return $rows_ref->[0]->[0];
}

sub tld_exists
{
	my $tld = shift;

	my $rows_ref = db_select(
		"select 1".
		" from hosts h,hosts_groups hg,groups g".
		" where h.hostid=hg.hostid".
			" and hg.groupid=g.groupid".
			" and g.name='TLDs'".
			" and h.status=0".
			" and h.host='$tld'");

	return 0 if (scalar(@$rows_ref) == 0);

	return 1;
}

sub tld_service_enabled
{
	my $tld = shift;
	my $service_type = shift;

	$service_type = uc($service_type) if (defined($service_type));

	return SUCCESS if (!defined($service_type) || ($service_type eq 'DNS'));

	my $host = "Template $tld";
	my $macro = "{\$RSM.TLD.$service_type.ENABLED}";

	my $rows_ref = db_select(
		"select hm.value".
		" from hosts h,hostmacro hm".
		" where h.hostid=hm.hostid".
			" and h.host='$host'".
			" and hm.macro='$macro'");

	fail("macro \"$macro\" does not exist at host \"$host\"") if (scalar(@$rows_ref) == 0);

	return ($rows_ref->[0]->[0] == 0 ? E_FAIL : SUCCESS);
}

sub handle_db_error
{
	my $msg = shift;

	my $prefix = "";

	$prefix = "[tld:$tld] " if ($tld);

	fail($prefix . "database error: $msg (query was: $_global_sql)");
}

sub db_connect
{
	fail("no database configuration defined") if (not defined($config) or
		not defined($config->{'db'}) or
		not defined($config->{'db'}->{'name'}));

	$_global_sql = 'DBI:mysql:'.$config->{'db'}->{'name'}.':'.$config->{'db'}->{'host'};

	$dbh = DBI->connect($_global_sql, $config->{'db'}->{'user'}, $config->{'db'}->{'password'},
		{
			PrintError  => 0,
			HandleError => \&handle_db_error,
		}) or handle_db_error(DBI->errstr);

	# improve performance of selects, see
	# http://search.cpan.org/~capttofu/DBD-mysql-4.028/lib/DBD/mysql.pm
	# for details
	$dbh->{'mysql_use_result'} = 1;
}

sub db_disconnect
{
     $dbh->disconnect();
}

sub db_select
{
	$_global_sql = shift;

	my $sec;
	if (opt('stats'))
	{
		$sec = time();
	}

	my $sth = $dbh->prepare($_global_sql)
		or fail("cannot prepare [$_global_sql]: ", $dbh->errstr);

	dbg("[$_global_sql]");

	my ($start, $exe, $fetch, $total);
	if (opt('warnslow'))
	{
		$start = time();
	}

	$sth->execute()
		or fail("cannot execute [$_global_sql]: ", $sth->errstr);

	if (opt('warnslow'))
	{
		$exe = time();
	}

	my $rows_ref = $sth->fetchall_arrayref();

	if (opt('warnslow'))
	{
		my $now = time();
		$total = $now - $start;

		if ($total > getopt('warnslow'))
		{
			$fetch = $now - $exe;
			$exe = $exe - $start;
			wrn("slow query: [$_global_sql] took ", sprintf("%.3f seconds (execute:%.3f fetch:%.3f)", $total, $exe, $fetch));
		}
	}

	if (opt('debug'))
	{
		my $rows = scalar(@$rows_ref);

		dbg("$rows row", ($rows != 1 ? "s" : ""));
	}

	if (opt('stats'))
	{
		$sql_time += time() - $sec;
		$sql_count++;
	}

	return $rows_ref;
}

sub db_select_binds
{
	$_global_sql = shift;
	my $bind_values = shift;

	my $sec;
	if (opt('stats'))
	{
		$sec = time();
	}

	my $sth = $dbh->prepare($_global_sql)
		or fail("cannot prepare [$_global_sql]: ", $dbh->errstr);

	dbg("[$_global_sql]");

	my ($start, $exe, $fetch, $total);

	my @rows;
	foreach my $bind_value (@$bind_values)
	{
		dbg("bind_value:$bind_value");

		if (opt('warnslow'))
		{
			$start = time();
		}

		$sth->execute($bind_value)
			or fail("cannot execute [$_global_sql] bind_value:$bind_value: ", $sth->errstr);

		if (opt('warnslow'))
		{
			$exe = time();
		}

		while (my @row = $sth->fetchrow_array())
		{
			push(@rows, \@row);
		}

		if (opt('warnslow'))
		{
			my $now = time();
			$total = $now - $start;

			if ($total > getopt('warnslow'))
			{
				$fetch = $now - $exe;
				$exe = $exe - $start;
				wrn("slow query: [$_global_sql] took ", sprintf("%.3f seconds (execute:%.3f fetch:%.3f)", $total, $exe, $fetch));
			}
		}
	}

	if (opt('debug'))
	{
		my $rows_num = scalar(@rows);

		dbg("$rows_num row", ($rows_num != 1 ? "s" : ""));
	}

	if (opt('stats'))
	{
		$sql_time += time() - $sec;
		$sql_count++;
	}

	return \@rows;
}

sub db_exec
{
	$_global_sql = shift;

	my $sec;
	if (opt('stats'))
	{
		$sec = time();
	}

	my $sth = $dbh->prepare($_global_sql)
		or fail("cannot prepare [$_global_sql]: ", $dbh->errstr);

	dbg("[$_global_sql]");

	my ($start, $total);
	if (opt('warnslow'))
	{
		$start = time();
	}

	$sth->execute()
		or fail("cannot execute [$_global_sql]: ", $sth->errstr);

	if (opt('warnslow'))
	{
		$total = time() - $start;

		if ($total > getopt('warnslow'))
		{
			wrn("slow query: [$_global_sql] took ", sprintf("%.3f seconds", $total));
		}
	}

	if (opt('stats'))
	{
		$sql_time += time() - $sec;
		$sql_count++;
	}

	return $sth->{mysql_insertid};
}

sub set_slv_config
{
	$config = shift;
}

# Get time bounds of the last cycle guaranteed to have probe results.
sub get_cycle_bounds
{
	my $now = shift;
	my $delay = shift;

	$now = int(time()) unless ($now);

	$now -= $delay + AVAIL_SHIFT_BACK;

	my $from = cycle_start($now, $delay);
	my $till = cycle_end($now, $delay);

	return ($from, $till, get_value_ts($till));
}

# Get time bounds of the rolling week, shift back to guarantee all probe results.
sub get_rollweek_bounds
{
	my $now = shift;	# specify different current time

	my $rollweek_seconds = __get_macro('{$RSM.ROLLWEEK.SECONDS}');

	my ($from, $till);

	$now = int(time()) unless ($now);

	$till = truncate_from($now) - ROLLWEEK_SHIFT_BACK;
	$from = $till - $rollweek_seconds;

	$till--;

	return ($from, $till, get_value_ts($till));
}

# Get bounds of previous month.
sub get_prev_month_bounds
{
	require DateTime;

	my $dt = DateTime->now;

	$dt->truncate(to => 'month');
	my $till = $dt->epoch - 1;

	$dt->subtract(months => 1);
	my $from = $dt->epoch;

	return ($from, $till, get_value_ts($till));
}

# Get bounds of the whole month. Value timestamp will be set for the latest completed cycle as for specified @now.
sub get_month_bounds
{
	require DateTime;

	my $now = shift;
	my $delay = shift;

	fail("internal error: get_month_bounds() requires 2 parameters: timestamp, delay") unless ($now && $delay);

	$now -= $delay + AVAIL_SHIFT_BACK;

	my $dt = DateTime->from_epoch(epoch => $now);

	$dt->truncate(to => 'month');
	my $from = $dt->epoch;

	$dt->add(months => 1);
	$dt->subtract(seconds => 1);
	my $till = $dt->epoch;

	my $value_ts = get_value_ts(cycle_end($now - $delay, $delay));

	return ($from, $till, $value_ts);
}

sub get_month_from
{
	require DateTime;

	my $now = shift;

	$now = int(time()) unless ($now);

	my $dt = DateTime->from_epoch(epoch => $now);

	$dt->truncate(to => 'month');

	return $dt->epoch;
}

sub get_num_cycles
{
	my $from = shift;
	my $till = shift;
	my $delay = shift;

	fail("internal error: invalid lower bound of time period specified: ", ts_full($from), ", expected 0th second of the minute")
		if (is_from_valid($from) != SUCCESS);
	fail("internal error: invalid upper bound of time period specified: ", ts_full($till), ", expected last second of the minute")
		if (is_till_valid($till) != SUCCESS);

	return ($till + 1 - $from) / $delay;
}

sub minutes_last_month
{
	require DateTime;

	my $dt = DateTime->now;

	$dt->truncate(to => 'month');
	my $till = $dt->epoch;

	$dt->subtract(months => 1);
	my $from = $dt->epoch;

	return ($till - $from) / 60;
}

# maximum timestamp for calculation of service availability
sub max_avail_time
{
	my $delay = shift;

	# truncate to the end of previous minute
	return cycle_end(time() - $delay - AVAIL_SHIFT_BACK, $delay);
}

sub __print_probe_times
{
	my $probe_times_ref = shift;

	if (scalar(keys(%{$probe_times_ref})) == 0)
	{
		info("no probes were online at given period");
		return;
	}

	info("probe online times:");

	foreach my $probe (keys(%{$probe_times_ref}))
	{
		info("  $probe");

		my $idx = 0;
		my $count = scalar(@{$probe_times_ref->{$probe}});

		while ($idx < $count)
		{
			my $from = $probe_times_ref->{$probe}->[$idx++];
			my $till = $probe_times_ref->{$probe}->[$idx++];

			info("    ", selected_period($from, $till));
		}
	}
}

# Get online times of probe nodes.
#
# Returns hash of probe names as keys and array with online times as values:
#
# {
#   'probe' => [ from1, till1, from2, till2 ... ]
#   ...
# }
#
# NB! If a probe was down for the whole specified period it won't be in a hash.
sub get_probe_times
{
	my $from = shift;
	my $till = shift;
	my $probes_ref = shift; # { host => hostid, ... }

	my $items_ref = db_select(
		"select i.itemid,h.host".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and i.templateid is not null".
			" and i.status<>".ITEM_STATUS_DISABLED.
			" and i.key_='".PROBE_KEY_ONLINE."'");

	my $result;

	if (scalar(@{$items_ref}) == 0)
	{
		wrn("Probe main status items (".PROBE_KEY_ONLINE.") not available, will calculate manually.");

		$result = get_probe_times2($from, $till, $probes_ref);

		__print_probe_times($result) if (opt('dry-run'));

		return $result;
	}

	foreach my $item_ref (@{$items_ref})
	{
		my $itemid = $item_ref->[0];
		my $host = $item_ref->[1];

		my $probe = substr($host, 0, -length(' - mon'));	# get rid of " - mon"

		my $values_ref = db_select(
			"select clock,value".
			" from history_uint".
			" where itemid=$itemid".
				" and clock between $from and $till");

		my $values_hash_ref;
		foreach my $row_ref (sort {$a->[0] <=> $b->[0]} (@{$values_ref}))	# sort by clock
		{
			$values_hash_ref->{truncate_from($row_ref->[0])} = $row_ref->[1];
		}

		my $step_clock = $from;
		my $step = 60;	# seconds
		my $prev_value = DOWN;

		# check probe status every minute, if the value is missing consider the probe down
		while ($step_clock < $till)
		{
			my $value = (defined($values_hash_ref->{$step_clock}) ? $values_hash_ref->{$step_clock} : DOWN);

			if ($prev_value == DOWN && $value == UP)
			{
				push(@{$result->{$probe}}, $step_clock);
			}
			elsif ($prev_value == UP && $value == DOWN)
			{
				# went down, add last second of previous minute
				push(@{$result->{$probe}}, $step_clock - 1);
			}

			$step_clock += $step;
			$prev_value = $value;
		}

		# push "till" to @times if it contains odd number of elements
		if ($result->{$probe})
		{
			push(@{$result->{$probe}}, $till) if ($prev_value == UP);
		}
	}

	__print_probe_times($result) if (opt('dry-run'));

	return $result;
}

# Old way of getting online probe statuses. Uses 3 methods
# - proxy lastaccess
# - automatic status (calculated by simple check on the probe, self-check)
# - manual status (manually turn off probe)
#
# Must return results the same way get_probe_times() does.
sub get_probe_times2
{
	my $from = shift;
	my $till = shift;
	my $probes_ref = shift; # { host => hostid, ... }

	my $probe_avail_limit = get_macro_probe_avail_limit();

	dbg("from:$from till:$till probe_avail_limit:$probe_avail_limit");

	$probes_ref = get_probes() unless (defined($probes_ref));

	my $result;

	# check probe lastaccess time
	foreach my $probe (keys(%$probes_ref))
	{
		my $host = "$probe - mon";

		my $itemid = get_itemid_by_host($host, PROBE_LASTACCESS_ITEM);
		if (!$itemid)
		{
			wrn("configuration error: ", rsm_slv_error());
			next;
		}

		my $times_ref = __get_lastaccess_times($itemid, $probe_avail_limit, $from, $till);

		my $hostid = $probes_ref->{$probe};

		if (scalar(@$times_ref) != 0)
		{
			dbg("$probe reachable times: ", join(',', @$times_ref)) if (opt('debug'));

			$times_ref = __get_probestatus_times($probe, $hostid, $times_ref, PROBE_KEY_MANUAL);
		}

		if (scalar(@$times_ref) != 0)
		{
			dbg("$probe manual probestatus times: ", join(',', @$times_ref)) if (opt('debug'));

			$times_ref = __get_probestatus_times($probe, $hostid, $times_ref, PROBE_KEY_AUTOMATIC);
		}

		if (scalar(@$times_ref) != 0)
		{
			dbg("$probe automatic probestatus times: ", join(',', @$times_ref)) if (opt('debug'));

			$result->{$probe} = $times_ref;
		}
	}

	return $result;
}

sub probe_offline_at
{
	my $probe_times_ref = shift;	# reference to a hash returned by get_probe_times()
	my $probe = shift;
	my $clock = shift;

	dbg("check if $probe was online at ", ts_full($clock));

	# if a probe was down for the whole period it won't be in a hash
	unless (exists($probe_times_ref->{$probe}))
	{
		dbg("does not exist in a hash, OFFLINE");
		return SUCCESS;	# offline
	}

	my $times_ref = $probe_times_ref->{$probe};

	my $clocks_count = scalar(@$times_ref);

	my $clock_index = 0;
	while ($clock_index < $clocks_count)
	{
		my $from = $times_ref->[$clock_index++];
		my $till = $times_ref->[$clock_index++];

		if (($from <= $clock) and ($clock <= $till))
		{
			dbg("found in online period: probe ONLINE");
			return E_FAIL;	# online
		}
	}

	dbg("not found in online periods: probe OFFLINE");

	return SUCCESS;	# offline
}

# Translate probe names to hostids of appropriate tld hosts.
#
# E. g., we have hosts (host/hostid):
#   "Probe2"		1
#   "Probe12"		2
#   "ORG Probe2"	100
#   "ORG Probe12"	101
# calling
#   probes2tldhostids("org", ("Probe2", "Probe12"))
# will return
#  (100, 101)
sub probes2tldhostids
{
	my $tld = shift;
	my $probe_times_ref = shift;
	my $clock = shift;	# optional

	my $result;

	my $hosts_str = '';
	foreach my $probe (keys(%{$probe_times_ref}))
	{
		next if ($clock && probe_offline_at($probe_times_ref, $probe, $clock) == SUCCESS);

		$hosts_str .= ' or ' unless ($hosts_str eq '');
		$hosts_str .= "host='$tld $probe'";
	}

	unless ($hosts_str eq "")
	{
		my $rows_ref = db_select("select hostid from hosts where $hosts_str");

		foreach my $row_ref (@$rows_ref)
		{
			push(@{$result}, $row_ref->[0]);
		}
	}

	return $result;
}

sub __probe2tldhostid
{
	my $tld = shift;
	my $probe = shift;

	my $rows_ref = db_select(
		"select hostid".
		" from hosts".
		" where host='$tld $probe'");

	return $rows_ref->[0]->[0] || fail("internal error: cannot get TLD-probe host ID ('$tld-$probe')");
}

sub get_probe_online_key_itemid
{
	my $probe = shift;

	return get_itemid_by_host("$probe - mon", PROBE_KEY_ONLINE);
}

sub init_values
{
	$_sender_values->{'data'} = [];

	if (opt('dry-run'))
	{
		$_sender_values->{'maxhost'} = 0;
		$_sender_values->{'maxkey'} = 0;
		$_sender_values->{'maxclock'} = 0;
		$_sender_values->{'maxvalue'} = 0;
	}
}

sub push_value
{
	my $hostname = shift;
	my $key = shift;
	my $clock = shift;
	my $value = shift;

	my $info = join('', @_);

	push(@{$_sender_values->{'data'}},
		{
			'data' =>
			{
				'host' => $hostname,
				'key' => $key,
				'value' => "$value",
				'clock' => $clock
			},
			'info' => $info,
		});

	if (opt('dry-run'))
	{
		my $hostlen = length($hostname);
		my $keylen = length($key);
		my $clocklen = length($clock);
		my $valuelen = length($value);

		$_sender_values->{'maxhost'} = $hostlen if (!$_sender_values->{'maxhost'} || $hostlen > $_sender_values->{'maxhost'});
		$_sender_values->{'maxkey'} = $keylen if (!$_sender_values->{'maxkey'} || $keylen > $_sender_values->{'maxkey'});
		$_sender_values->{'maxclock'} = $clocklen if (!$_sender_values->{'maxclock'} || $clocklen > $_sender_values->{'maxclock'});
		$_sender_values->{'maxvalue'} = $valuelen if (!$_sender_values->{'maxvalue'} || $valuelen > $_sender_values->{'maxvalue'});
	}
}

#
# send previously collected values:
#
# [
#   {'host' => 'host1', 'key' => 'item1', 'value' => '5', 'clock' => 1391790685},
#   {'host' => 'host2', 'key' => 'item1', 'value' => '4', 'clock' => 1391790685},
#   ...
# ]
#
sub send_values
{
	if (opt('dry-run'))
	{
		my $mh = $_sender_values->{'maxhost'};
		my $mk = $_sender_values->{'maxkey'};
		my $mv = $_sender_values->{'maxvalue'};
		my $mc = $_sender_values->{'maxclock'};

		my $fmt = "%-${mh}s | %${mk}s | %-${mv}s | %-${mc}s | %s";

		# $tld is a global variable which is used in info()
		foreach my $h (@{$_sender_values->{'data'}})
		{
			my $msg = sprintf($fmt,
				$h->{'data'}->{'host'},
				$h->{'data'}->{'key'},
				$h->{'data'}->{'value'},
				ts_full($h->{'data'}->{'clock'}),
				$h->{'info'});

			info($msg);
		}

		return;
	}

	if (scalar(@{$_sender_values->{'data'}}) == 0)
	{
		wrn("will not send values, nothing to send");
		return;
	}

	my $sender = Zabbix::Sender->new({
		'server' => $config->{'slv'}->{'zserver'},
		'port' => $config->{'slv'}->{'zport'},
		'timeout' => 10,
		'retries' => 5 });

	fail("cannot connect to Zabbix server") unless (defined($sender));

	my $total_values = scalar(@{$_sender_values->{'data'}});

	while (scalar(@{$_sender_values->{'data'}}) > 0)
	{
		my @suba = splice(@{$_sender_values->{'data'}}, 0, SENDER_BATCH_COUNT);

		dbg("sending ", scalar(@suba), "/$total_values values");

		my @hashes;

		foreach my $hash_ref (@suba)
		{
			push(@hashes, $hash_ref->{'data'});
		}

		unless (defined($sender->send_arrref(\@hashes)))
		{
			my $msg = "Cannot send data to Zabbix server: " . $sender->sender_err() . ". The query was:";

			foreach my $hash_ref (@suba)
			{
				my $data_ref = $hash_ref->{'data'};

				my $line = '{';

				$line .= ($line ne '{' ? ', ' : '') . $_ . ' => ' . $data_ref->{$_} foreach (keys(%$data_ref));

				$line .= '}';

				$msg .= "\n  $line";
			}

			fail($msg);
		}

		# $tld is a global variable which is used in info()
		my $saved_tld = $tld;
		foreach my $hash_ref (@suba)
		{
			$tld = $hash_ref->{'tld'};
			info($hash_ref->{'data'}->{'key'}, '=', $hash_ref->{'data'}->{'value'}, " ", $hash_ref->{'info'});
		}
		$tld = $saved_tld;
	}
}

# Get name server details (name, IP) from item key.
#
# E. g.:
#
# rsm.dns.udp.rtt[{$RSM.TLD},i.ns.se.,194.146.106.22] -> "i.ns.se.,194.146.106.22"
# rsm.slv.dns.avail[i.ns.se.,194.146.106.22] -> "i.ns.se.,194.146.106.22"
sub get_nsip_from_key
{
	my $key = shift;

	$key =~ s/^[^\[]+\[([^\]]+)]$/$1/;

	my $got_params = 0;
	my $pos = length($key);

	while ($pos > 0 and $got_params < 2)
	{
		$pos--;
		my $char = substr($key, $pos, 1);
		$got_params++ if ($char eq ',')
	}

	$pos == 0 ? $got_params++ : $pos++;

	return "" unless ($got_params == 2);

	return substr($key, $pos);
}

sub get_ip_from_nsip
{
	my $nsip = shift;

	my $index = index($nsip, ",");

	if ($index == -1)
	{
		fail("internal error: invalid NS,IP pair: $nsip");
	}

	return substr($nsip, $index + 1);
}

sub is_service_error
{
	my $error = shift;

	return SUCCESS if ($error <= MAX_SERVICE_ERROR);

	return E_FAIL;
}

sub process_slv_monthly
{
	my $tld = shift;
	my $cfg_key_in = shift;		# input key, e. g. 'rsm.rdds.43.rtt[{$RSM.TLD}]'
	my $from = shift;		# start of the period
	my $till = shift;		# end of the period
	my $value_ts = shift;		# value timestamp
	my $delay = shift;		# item delay
	my $probe_times_ref = shift;	# reference to the probes that were online
	my $check_value_ref = shift;	# a pointer to subroutine to check if the value was successful

	my $result;

	$result->{'total_tests'} = 0;
	$result->{'failed_tests'} = 0;
	$result->{'successful_tests'} = 0;
	$result->{'successful_accum'} = 0;	# accumulated successful values, to get the average later

	my $hostids_ref = probes2tldhostids($tld, $probe_times_ref);
	if (!$hostids_ref)
	{
		wrn("no probes found");
		return $result;
	}

	my $items_ref = get_items_by_hostids($hostids_ref, $cfg_key_in, 1);	# complete key
	if (scalar(@$items_ref) == 0)
	{
		wrn("no items ($cfg_key_in) found");
		return $result;
	}

	my $values_ref = __get_item_values($items_ref, $from, $till, ITEM_VALUE_TYPE_FLOAT);

	foreach my $itemid (keys(%{$values_ref}))
	{
		foreach my $clock (keys(%{$values_ref->{$itemid}}))
		{
			my $value = $values_ref->{$itemid}->{$clock};

			$result->{'total_tests'}++;

			if ($check_value_ref->($value) == SUCCESS)
			{
				$result->{'successful_tests'}++;
				$result->{'successful_accum'} += $value;
			}
			else
			{
				$result->{'failed_tests'}++;
			}
		}
	}

	return $result;
}

sub process_slv_ns_monthly
{
	my $tld = shift;
	my $cfg_key_in = shift;		# part of input key, e. g. 'rsm.dns.tcp.rtt[{$RSM.TLD},'
	my $from = shift;		# start of the period
	my $till = shift;		# end of the period
	my $value_ts = shift;		# value timestamp
	my $delay = shift;		# item delay
	my $probe_times_ref = shift;	# reference to the probes that were online
	my $check_value_ref = shift;	# a pointer to subroutine to check if the value was successful

	my $nsips_ref = get_templated_nsips($tld, $cfg_key_in);

	dbg("using filter '$cfg_key_in' found next NS:\n", Dumper($nsips_ref)) if (opt('debug'));

	my $result;

	$result->{'total_tests'} = 0;
	$result->{'failed_tests'} = 0;
	$result->{'successful_tests'} = 0;
	$result->{'successful_accum'} = 0;	# accumulated successful values, to get the average later
	$result->{'ipv4_addresses'} = 0;
	$result->{'ipv6_addresses'} = 0;

	foreach my $nsip (@$nsips_ref)
	{
		my $ip_version = get_ip_version(get_ip_from_nsip($nsip));

		if ($ip_version == 4)
		{
			$result->{'ipv4_addresses'}++;
		}
		else
		{
			$result->{'ipv6_addresses'}++;
		}
	}

	my $nsip_items_ref = get_nsip_items($nsips_ref, $cfg_key_in, $tld);

	dbg("using filter '$cfg_key_in' found next name server items:\n", Dumper($nsip_items_ref)) if (opt('debug'));

	foreach my $probe (keys(%{$probe_times_ref}))
	{
		my $times_ref = $probe_times_ref->{$probe};

		my $hostid = __probe2tldhostid($tld, $probe);

		my $itemids_ref = get_itemids_by_hostids([$hostid], $nsip_items_ref);
		my $values_ref = __get_nsip_values($itemids_ref, $times_ref, $nsip_items_ref);

		foreach my $nsip (keys(%$values_ref))
		{
			foreach my $itemid (keys(%{$values_ref->{$nsip}}))
			{
				foreach my $clock (keys(%{$values_ref->{$nsip}->{$itemid}}))
				{
					my $value = $values_ref->{$nsip}->{$itemid}->{$clock};

					$result->{'total_tests'}++;

					if ($check_value_ref->($value) == SUCCESS)
					{
						$result->{'successful_tests'}++;
						$result->{'successful_accum'} += $value;
					}
					else
					{
						$result->{'failed_tests'}++;
					}
				}
			}
		}
	}

	return $result;
}

sub process_slv_avail
{
	my $tld = shift;
	my $cfg_key_in = shift;
	my $from = shift;
	my $till = shift;
	my $cfg_minonline = shift;
	my $probe_times_ref = shift;
	my $check_value_ref = shift;

	my $online_probes_count = scalar(keys(%{$probe_times_ref}));

	my $result;

	if ($online_probes_count < $cfg_minonline)
	{
		$result->{'value'} = UP_INCONCLUSIVE;
		$result->{'message'} = "Up (inconclusive): not enough probes online, $online_probes_count while $cfg_minonline required";
		$result->{'alert'} = 1 if (alerts_enabled() == SUCCESS);

		return $result;
	}

	my $hostids_ref = probes2tldhostids($tld, $probe_times_ref, $from);
	if (!$hostids_ref)
	{
		$result->{'value'} = UP_INCONCLUSIVE;
		$result->{'message'} = "Up (inconclusive): no online probes";
		$result->{'alert'} = 1 if (alerts_enabled() == SUCCESS);

		return $result;
	}

	my $complete_key = ("]" eq substr($cfg_key_in, -1)) ? 1 : 0;
	my $items_ref = get_items_by_hostids($hostids_ref, $cfg_key_in, $complete_key);
	if (scalar(@$items_ref) == 0)
	{
		wrn("no items ($cfg_key_in) found");
		return $result;
	}

	my $values_ref = __get_item_values($items_ref, $from, $till, ITEM_VALUE_TYPE_UINT64);
	my $probes_with_results = scalar(keys(%{$values_ref}));
	if ($probes_with_results < $cfg_minonline)
	{
		$result->{'value'} = UP_INCONCLUSIVE;
		$result->{'message'} = "Up (inconclusive): not enough probes with reults, $probes_with_results while $cfg_minonline required";
		$result->{'alert'} = 1 if (alerts_enabled() == SUCCESS);

		return $result;
	}

	my $probes_with_positive = 0;
	foreach my $itemid (keys(%{$values_ref}))
	{
		foreach my $clock (keys(%{$values_ref->{$itemid}}))
		{
			my $result = $check_value_ref->($values_ref->{$itemid}->{$clock});

			if (SUCCESS == $result)
			{
				$probes_with_positive++;
				last;
			}

			if (opt('debug'))
			{
				my $hostid = -1;
				foreach my $item (@$items_ref)
				{
					if ($item->{'itemid'} == $itemid)
					{
						$hostid = $item->{'hostid'};
						last;
					}
				}

				dbg("i:$itemid (h:$hostid): ", (SUCCESS == $result ? "up" : "down"), " (values: ", join(', ', @{$values_ref->{$itemid}}), ")");
			}
		}
	}

	my $perc = $probes_with_positive * 100 / $probes_with_results;
	my $test_result = $perc > SLV_UNAVAILABILITY_LIMIT ? UP : DOWN;

	$result->{'value'} = $test_result;
	$result->{'message'} = avail_result_msg($test_result, $probes_with_positive, $probes_with_results, $perc);

	return $result;
}

sub process_slv_ns_avail
{
	my $tld = shift;
	my $cfg_key_in = shift;
	my $from = shift;
	my $till = shift;
	my $cfg_minonline = shift;
	my $probe_times_ref = shift;
	my $check_value_ref = shift;

	my $nsips_ref = get_templated_nsips($tld, $cfg_key_in);

	dbg("using filter '$cfg_key_in' found next name servers:\n", Dumper($nsips_ref)) if (opt('debug'));

	my $nsip_results;

	foreach my $nsip (@{$nsips_ref})
	{
		$nsip_results->{$nsip}->{'probes_with_results'} = 0;
		$nsip_results->{$nsip}->{'probes_with_positive'} = 0;
	}

	my $online_probes_count = scalar(keys(%{$probe_times_ref}));

	if ($online_probes_count >= $cfg_minonline)
	{
		my $nsip_items_ref = get_nsip_items($nsips_ref, $cfg_key_in, $tld);

		foreach my $probe (keys(%$probe_times_ref))
		{
			my $times_ref = $probe_times_ref->{$probe};

			my $hostid = __probe2tldhostid($tld, $probe);

			my $itemids_ref = get_itemids_by_hostids([$hostid], $nsip_items_ref);
			my $values_ref = __get_nsip_values($itemids_ref, $times_ref, $nsip_items_ref);

			foreach my $nsip (keys(%$values_ref))
			{
				foreach my $itemid (keys(%{$values_ref->{$nsip}}))
				{
					$nsip_results->{$nsip}->{'probes_with_results'}++;

					# consider only one (best) cycle test
					foreach my $clock (keys(%{$values_ref->{$nsip}->{$itemid}}))
					{
						my $value = $values_ref->{$nsip}->{$itemid}->{$clock};

						dbg($value);

						if ($check_value_ref->($value) == SUCCESS)
						{
							$nsip_results->{$nsip}->{'probes_with_positive'}++;
							last;
						}
					}
				}
			}
		}
	}

	foreach my $nsip (@{$nsips_ref})
	{
		my ($status_value, $status_message);

		if ($online_probes_count < $cfg_minonline)
		{
			$status_value = UP_INCONCLUSIVE;
			$status_message = "not enough probes online, $online_probes_count while $cfg_minonline required";

			$result->{$nsip}->{'alert'} = 1 if (alerts_enabled() == SUCCESS);
		}
		elsif ($nsip_results->{$nsip}->{'probes_with_results'} < $cfg_minonline)
		{
			$status_value = UP_INCONCLUSIVE;
			$status_message = "not enough probes with reults, " .
				$nsip_results->{$nsip}->{'probes_with_results'} . " while $cfg_minonline required";

			$result->{$nsip}->{'alert'} = 1 if (alerts_enabled() == SUCCESS);
		}
		else
		{
			my $perc = $nsip_results->{$nsip}->{'probes_with_positive'} * 100 / $nsip_results->{$nsip}->{'probes_with_results'};
			my $test_result = $perc > SLV_UNAVAILABILITY_LIMIT ? UP : DOWN;

			$status_value = $test_result;
			$status_message = avail_result_msg($test_result, $nsip_results->{$nsip}->{'probes_with_positive'},
				$nsip_results->{$nsip}->{'probes_with_results'}, $perc);
		}

		$result->{$nsip}->{'value'} = $status_value;
		$result->{$nsip}->{'message'} = $status_message;
	}

	return $result;
}

sub process_slv_downtime
{
	my $from = shift;
	my $till = shift;
	my $value_ts = shift;
	my $key_in = shift;
	my $key_out = shift;
	my $tlds_ref = shift;

	my $tld_items;

	# just collect itemids
	foreach (@$tlds_ref)
	{
		$tld = $_;	# set global variable here

		my $itemid_in = get_itemid_by_host($tld, $key_in);

		next unless ($itemid_in);

		if (!opt('dry-run'))
		{
			my $itemid_out = get_itemid_by_host($tld, $key_out);

			next unless ($itemid_out);

			next if (uint_value_exists($value_ts, $itemid_out) == SUCCESS);
		}

		# for future calculation of downtime
		$tld_items->{$tld} = $itemid_in;
	}

	# unset TLD (for the logs)
	$tld = undef;

	my $result;

	# use bind for faster execution of the same SQL query
	my $sth = get_downtime_prepare();

	foreach (keys(%{$tld_items}))
	{
		$tld = $_;	# set global variable here

		my $itemid = $tld_items->{$tld};

		$result->{$tld} = get_downtime_execute($sth, $itemid, $from, $till);
	}

	# unset TLD (for the logs)
	$tld = undef;

	return $result;
}

#
# get total and successful number of results of a service within given period of time for
# a specified TLD
#
sub get_results
{
	my $tld = shift;
	my $probe_times_ref = shift;	# probe online times (for history data)
	my $items_ref = shift;		# list of items to get results
	my $check_value_ref = shift;	# a pointer to subroutine to check if the value was successful

	my $result;
	foreach my $hostid (keys(%$items_ref))
	{
		my $hostitems = $items_ref->{$hostid};
		foreach my $itemid (keys(%$hostitems))
		{
			my $nsip = $items_ref->{$hostid}->{$itemid};
			$result->{$nsip} = {'total' => 0, 'successful' => 0} unless (exists($result->{$nsip}));
		}
	}

	foreach my $probe (keys(%$probe_times_ref))
	{
		my $times_ref = $probe_times_ref->{$probe};
		my $hostids_ref = probes2tldhostids($tld, [$probe]);
		my $itemids_ref = get_itemids_by_hostids($hostids_ref, $items_ref);
		my $values_ref = __get_nsip_values($itemids_ref, $times_ref, $items_ref);

		foreach my $nsip (keys(%$values_ref))
		{
			foreach my $itemid (keys(%{$values_ref->{$nsip}}))
			{
				foreach my $clock (keys(%{$values_ref->{$nsip}->{$itemid}}))
				{
					my $value = $values_ref->{$nsip}->{$itemid}->{$clock};

					$result->{$nsip}->{'total'}++;

					if ($check_value_ref->($value) == SUCCESS)
					{
						$result->{$nsip}->{'successful'}++;
					}
				}
			}

			dbg("[$probe] $nsip: ", $result->{$nsip}->{'successful'}, "/", $result->{$nsip}->{'total'});
		}
	}

	return $result;
}

# organize values from all hosts grouped by itemid and return itemid->clock->value hash
#
# E. g.:
#
# '10010' => {1418273230 -> 143},
# '10011' => {1418273230 -> 26}
# ...
sub __get_item_values
{
	my $items_ref = shift;
	my $from = shift;
	my $till = shift;
	my $value_type = shift;

	my $table;
	if ($value_type == ITEM_VALUE_TYPE_UINT64)
	{
		$table = 'history_uint';
	}
	elsif ($value_type == ITEM_VALUE_TYPE_FLOAT)
	{
		$table = 'history';
	}
	elsif ($value_type == ITEM_VALUE_TYPE_STR)
	{
		$table = 'history_str';
	}
	else
	{
		fail("THIS SHOULD NEVER HAPPEN: $value_type: unknown value_type");
	}

	my $result;

	if (0 != scalar(@$items_ref))
	{
		my $itemids_str = "";
		foreach (@$items_ref)
		{
			$itemids_str .= "," unless ($itemids_str eq "");
			$itemids_str .= $_->{'itemid'};
		}

		my $rows_ref = db_select(
			"select itemid,value,clock".
			" from $table".
			" where itemid in ($itemids_str)".
				" and clock between $from and $till");

		foreach my $row_ref (@$rows_ref)
		{
			my $itemid = $row_ref->[0];
			my $value = $row_ref->[1];
			my $clock = $row_ref->[2];

			if (!defined($result->{$itemid}) || !defined($result->{$itemid}->{$clock}) ||
					($value >= 0 && $value < $result->{$itemid}->{$clock}))
			{
				$result->{$itemid}->{$clock} = $value;
			}
		}
	}

	return $result;
}

sub uint_value_exists
{
        my $clock = shift;
        my $itemid = shift;

        my $rows_ref = db_select("select 1 from history_uint where itemid=$itemid and clock=$clock");

        return SUCCESS if ($rows_ref->[0]->[0]);

        return E_FAIL;
}

sub dbl_value_exists
{
        my $clock = shift;
        my $itemid = shift;

        my $rows_ref = db_select("select 1 from history where itemid=$itemid and clock=$clock");

        return SUCCESS if ($rows_ref->[0]->[0]);

        return E_FAIL;
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
sub get_dns_itemids
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
			" and i.status<>".ITEM_STATUS_DISABLED.
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
			" and i.status<>".ITEM_STATUS_DISABLED.
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
sub get_rdds_dbl_itemids
{
	my $tld = shift;
	my $probe = shift;
	my $key_43_rtt = shift;
	my $key_80_rtt = shift;
	my $key_43_upd = shift;

	return __get_itemids_by_complete_key($tld, $probe, $key_43_rtt, $key_80_rtt, $key_43_upd);
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
sub get_rdds_str_itemids
{
	my $tld = shift;
	my $probe = shift;
	my $key_43_ip = shift;
	my $key_80_ip = shift;

	return __get_itemids_by_complete_key($tld, $probe, $key_43_ip, $key_80_ip);
}

# call this function with list of incomplete keys after $tld, e. g.:
# __get_itemids_by_incomplete_key("example", "aaa[", "bbb[", ...)
sub __get_itemids_by_incomplete_key
{
	my $tld = shift;
	my $probe = shift;

	my $keys_cond = "(key_ like '" . join("%' or key_ like '", @_) . "%')";

	my $host_value = ($probe ? "$tld $probe" : "$tld %");

	my $rows_ref = db_select(
		"select h.host,i.itemid,i.key_".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and i.status<>".ITEM_STATUS_DISABLED.
			" and h.host like '$host_value'".
			" and i.templateid is not null".
			" and $keys_cond");

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

	fail("cannot find items ('", join("','", @_), "') at host ($tld *)") if (scalar(keys(%result)) == 0);

	return \%result;
}

sub get_epp_dbl_itemids
{
	my $tld = shift;
	my $probe = shift;
	my $key_rtt = shift;

	return __get_itemids_by_incomplete_key($tld, $probe, $key_rtt);
}

sub get_epp_str_itemids
{
	my $tld = shift;
	my $probe = shift;
	my $key_ip = shift;

	return __get_itemids_by_complete_key($tld, $probe, $key_ip);
}

sub __best_rtt
{
	my $cur_rtt = shift;
	my $cur_description = shift;
	my $new_rtt = shift;
	my $new_description = shift;

	if (!defined($cur_rtt) && !defined($cur_description))
	{
		return ($new_rtt, $new_description);
	}

	if (defined($new_rtt))
	{
		if (!defined($cur_rtt) || $cur_rtt > $new_rtt)
		{
			return ($new_rtt, $new_description);
		}
	}

	return ($cur_rtt, $cur_description);
}

# values are organized like this:
#
# {
#     1418994681 =>
#     {
#         'DNS' =>
#         {
#             'WashingtonDC' =>
#             {
#                 'ns1' =>
#                 [
#                     {
#                         'targetIP' => 192.0.34.201,
#                         'rtt' => 103,
#                         'clock' => 1418994681,
#                         'description' => null
#                     },
#                     {
#                         'targetIP' => 2620:0:2d0:270::1:201,
#                         'rtt' => null,
#                         'clock' => 1418994681
#                         'description' => -204
#                     }
#                 ]
#             }
#         }
#     }
# }
#
# {$cycleclock}->{$interface}->{$probe}->{$ns}
sub get_dns_test_values
{
	my $dns_items_ref = shift;
	my $start = shift;
	my $end = shift;
	my $valuemaps = shift;
	my $delay = shift;
	my $service = shift;

	my $interface;

	if (uc($service) eq 'DNS')
	{
		$interface = JSON_INTERFACE_DNS;
	}
	else
	{
		$interface = JSON_INTERFACE_DNSSEC;
	}

	my $result;

	# generate list if itemids
	my @itemids;
	foreach my $probe (keys(%$dns_items_ref))
	{
		push(@itemids, keys(%{$dns_items_ref->{$probe}}));
	}

	if (scalar(@itemids) != 0)
	{
		my $rows_ref = db_select_binds("select itemid,value,clock from history where itemid=? and " . sql_time_condition($start, $end), \@itemids);

		foreach my $row_ref (sort { $a->[2] <=> $b->[2] } @$rows_ref)
		{
			my $itemid = $row_ref->[0];
			my $value = $row_ref->[1];
			my $clock = $row_ref->[2];

			my ($nsip, $probe);
			my $last = 0;

			foreach my $pr (keys(%$dns_items_ref))
			{
				my $itemids_ref = $dns_items_ref->{$pr};

				foreach my $i (keys(%$itemids_ref))
				{
					if ($i == $itemid)
					{
						$nsip = $dns_items_ref->{$pr}->{$i};
						$probe = $pr;
						$last = 1;
						last;
					}
				}
				last if ($last == 1);
			}

			unless (defined($nsip))
			{
				wrn("internal error: Name Server,IP pair of item $itemid not found");
				next;
			}

			my ($target, $ip) = split(',', $nsip);

			my ($new_value, $new_description, $set_idx);

			my $value_tag = JSON_TAG_RTT();

			$new_value = $value;

			if ($new_value < 0)
			{
				$new_description = $new_value;
				undef($new_value);
			}

			my $cycleclock = cycle_start($clock, $delay);

			my $tests_ref = $result->{$cycleclock}->{$interface}->{$probe}->{$target};

			my $idx = 0;
			foreach my $test_ref (@$tests_ref)
			{
				if ($test_ref->{JSON_TAG_TARGET_IP()} eq $ip)
				{
					$set_idx = $idx;
					last;
				}

				$idx++;
			}

			if (!defined($set_idx))
			{
				$set_idx = 0;
			}
			else
			{
				my $test_ref = $tests_ref->[$set_idx];

				($new_value, $new_description) = __best_rtt($test_ref->{$value_tag}, $test_ref->{JSON_TAG_DESCRIPTION()}, $new_value, $new_description);

				if (!defined($new_value) || (defined($test_ref->{$value_tag}) && $new_value == $test_ref->{$value_tag}))
				{
					undef($set_idx);
				}
			}

			if (defined($set_idx))
			{
				$result->{$cycleclock}->{$interface}->{$probe}->{$target}->[$set_idx] =
				{
					JSON_TAG_TARGET_IP() => $ip,
					$value_tag => $new_value,
					JSON_TAG_CLOCK() => $clock,
					JSON_TAG_DESCRIPTION() => get_detailed_result($valuemaps, $new_description)
				};
			}
		}
	}

	return $result;
}

sub __find_probe_key_by_itemid
{
	my $itemid = shift;
	my $items_ref = shift;

	my ($probe, $key);
	my $last = 0;

	foreach my $pr (keys(%$items_ref))
	{
		my $itemids_ref = $items_ref->{$pr};

		foreach my $i (keys(%$itemids_ref))
		{
			if ($i == $itemid)
			{
				$probe = $pr;
				$key = $items_ref->{$pr}->{$i};
				$last = 1;
				last;
			}
		}
		last if ($last == 1);
	}

	return ($probe, $key);
}

sub __get_rdds_port
{
	my $key = shift;

	# rsm.rdds.43... <-- returns 43 or 80
	return substr($key, 9, 2);
}

sub __get_rdds_dbl_type
{
	my $key = shift;

	# rsm.rdds.43.rtt... rsm.rdds.43.upd[... <-- returns "rtt" or "upd"
	return substr($key, 12, 3);
}

sub __get_rdds_str_type
{
	# NB! This is done for consistency, perhaps in the future there will be more string items, not just "ip".
	return 'ip';
}

sub __get_epp_dbl_type
{
	my $key = shift;

	chop($key); # remove last char ']'

	# rsm.epp.rtt[{$RSM.TLD},login <-- returns "login" (other options: "update", "info")
        return substr($key, 23);
}

sub __get_epp_str_type
{
	# NB! This is done for consistency, perhaps in the future there will be more string items, not just "ip".
	return 'ip';
}

# values are organized like this:
#
# {
#     1418994681 =>
#     {
#         'RDDS43' =>
#         {
#             'WashingtonDC' =>
#             {
#                 'ns1' =>
#                 [
#                     {
#                         'targetIP' => 192.0.34.201,
#                         'rtt' => 103,
#                         'upd' => 23,
#                         'clock' => 1418994681,
#                         'description' => null
#                     },
#                     {
#                         'targetIP' => 2620:0:2d0:270::1:201,
#                         'rtt' => null,
#                         'upd' => null,
#                         'clock' => 1418994681,
#                         'description' => -204
#                     }
#                 ]
#             }
#         }
#         'RDDS80' =>
#         {
#             'WashingtonDC' =>
#             {
#                 'ns1' =>
#                 [
#                     {
#                         'targetIP' => 192.0.34.201,
#                         'rtt' => 103,
#                         'clock' => 1418994681,
#                         'description' => null
#                     },
#                     {
#                         'targetIP' => 2620:0:2d0:270::1:201,
#                         'rtt' => null,
#                         'clock' => 1418994681,
#                         'description' => -204
#                     }
#                 ]
#             }
#         }
#     }
# }
sub get_rdds_test_values
{
	my $rdds_dbl_items_ref = shift;
	my $rdds_str_items_ref = shift;
	my $start = shift;
	my $end = shift;
	my $valuemaps = shift;
	my $delay = shift;

	# generate list if itemids
	my @dbl_itemids;
	foreach my $probe (keys(%$rdds_dbl_items_ref))
	{
		foreach my $itemid (keys(%{$rdds_dbl_items_ref->{$probe}}))
		{
			push(@dbl_itemids, $itemid);
		}
	}

	my @str_itemids;
	foreach my $probe (keys(%$rdds_str_items_ref))
	{
		foreach my $itemid (keys(%{$rdds_str_items_ref->{$probe}}))
		{
			push(@str_itemids, $itemid);
		}
	}

	return undef if (scalar(@dbl_itemids) == 0 || scalar(@str_itemids) == 0);

	my $result;
	my $target = '';

	my $dbl_rows_ref = db_select_binds("select itemid,value,clock from history where itemid=? and " . sql_time_condition($start, $end), \@dbl_itemids);

	foreach my $row_ref (sort { $a->[2] <=> $b->[2] } @$dbl_rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $value = $row_ref->[1];
		my $clock = $row_ref->[2];

		my ($probe, $key) = __find_probe_key_by_itemid($itemid, $rdds_dbl_items_ref);

		fail("internal error: cannot get Probe-key pair by itemid:$itemid")
			unless (defined($probe) and defined($key));

		my $port = __get_rdds_port($key);
		my $type = __get_rdds_dbl_type($key);	# rtt (double) or upd (int, if EPP is enabled)

		my $interface;
		if ($port eq '43')
		{
			$interface = JSON_INTERFACE_RDDS43;
		}
		elsif ($port eq '80')
		{
			$interface = JSON_INTERFACE_RDDS80;
		}
		else
		{
			fail("unknown RDDS port in item (id:$itemid)");
		}

		my ($new_value, $new_description, $value_tag, $set_idx);

		if ($type eq 'rtt')
		{
			$value_tag = JSON_TAG_RTT;
			$new_value = $value;
		}
		elsif ($type eq 'upd')
		{
			$value_tag = JSON_TAG_UPD;
			$new_value = int($value);
		}
		else
		{
			fail("unknown $interface item key (itemid:$itemid), expected 'rtt' or 'upd' value");
		}

		if ($new_value < 0)
		{
			$new_description = get_detailed_result($valuemaps, $new_value);
			undef($new_value);
		}

		my $cycleclock = cycle_start($clock, $delay);

		my $test_ref = $result->{$cycleclock}->{$interface}->{$probe}->{$target}->[0];

		$test_ref->{$value_tag} = $new_value;
		$test_ref->{JSON_TAG_CLOCK()} = $clock;
		$test_ref->{JSON_TAG_DESCRIPTION()} = $new_description;
	}

	my $str_rows_ref = db_select_binds("select itemid,value,clock from history_str where itemid=? and " . sql_time_condition($start, $end), \@str_itemids);

	foreach my $row_ref (sort { $a->[2] <=> $b->[2] } @$str_rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $ip = $row_ref->[1];
		my $clock = $row_ref->[2];

		my ($probe, $key) = __find_probe_key_by_itemid($itemid, $rdds_str_items_ref);

		fail("internal error: cannot get Probe-key pair by itemid:$itemid") unless (defined($probe) and defined($key));

		my $port = __get_rdds_port($key);
		my $type = __get_rdds_str_type($key);

		my $interface;
                if ($port eq '43')
                {
                        $interface = JSON_INTERFACE_RDDS43;
                }
                elsif ($port eq '80')
                {
                        $interface = JSON_INTERFACE_RDDS80;
                }
                else
                {
                        fail("unknown RDDS port in item (id:$itemid)");
                }

		if ($type ne 'ip')
		{
			fail("internal error: unknown item key (itemid:$itemid), expected item key representing the IP involved in $interface test");
		}

		my $cycleclock = cycle_start($clock, $delay);

		my $test_ref = $result->{$cycleclock}->{$interface}->{$probe}->{$target}->[0];

		$test_ref->{JSON_TAG_TARGET_IP()} = $ip;
	}

	return $result;
}

# values are organized like this:
# {
#         'WashingtonDC' => {
#                 '1418994206' => {
#                               'ip' => '192.0.34.201',
#                               'login' => '127.0000',
#                               'update' => '366.0000'
#                               'info' => '366.0000'
#                 },
#                 '1418994456' => {
#                               'ip' => '192.0.34.202',
#                               'login' => '121.0000',
#                               'update' => '263.0000'
#                               'info' => '321.0000'
#                 },
# ...
sub get_epp_test_values
{
	my $epp_dbl_items_ref = shift;
	my $epp_str_items_ref = shift;
	my $start = shift;
	my $end = shift;

	my %result;

	# generate list if itemids
	my $dbl_itemids_str = '';
	foreach my $probe (keys(%$epp_dbl_items_ref))
	{
		my $itemids_ref = $epp_dbl_items_ref->{$probe};

		foreach my $itemid (keys(%$itemids_ref))
		{
			$dbl_itemids_str .= ',' unless ($dbl_itemids_str eq '');
			$dbl_itemids_str .= $itemid;
		}
	}

	my $str_itemids_str = '';
	foreach my $probe (keys(%$epp_str_items_ref))
	{
		my $itemids_ref = $epp_str_items_ref->{$probe};

		foreach my $itemid (keys(%$itemids_ref))
		{
			$str_itemids_str .= ',' unless ($str_itemids_str eq '');
			$str_itemids_str .= $itemid;
		}
	}

	return \%result if ($dbl_itemids_str eq '' or $str_itemids_str eq '');

	my $dbl_rows_ref = db_select("select itemid,value,clock from history where itemid in ($dbl_itemids_str) and " . sql_time_condition($start, $end). " order by clock");

	foreach my $row_ref (@$dbl_rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $value = $row_ref->[1];
		my $clock = $row_ref->[2];

		my ($probe, $key) = __find_probe_key_by_itemid($itemid, $epp_dbl_items_ref);

		fail("internal error: cannot get Probe-key pair by itemid:$itemid") unless (defined($probe) and defined($key));

		my $type = __get_epp_dbl_type($key);

		$result{$probe}->{$clock}->{$type} = $value;
	}

	my $str_rows_ref = db_select("select itemid,value,clock from history_str where itemid in ($str_itemids_str) and " . sql_time_condition($start, $end). " order by clock");

	foreach my $row_ref (@$str_rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $value = $row_ref->[1];
		my $clock = $row_ref->[2];

		my ($probe, $key) = __find_probe_key_by_itemid($itemid, $epp_str_items_ref);

		fail("internal error: cannot get Probe-key pair by itemid:$itemid") unless (defined($probe) and defined($key));

		my $type = __get_epp_str_type($key);

		$result{$probe}->{$clock}->{$type} = $value;
	}

	return \%result;
}

sub no_cycle_result
{
	my $service = shift;
	my $avail_key = shift;
	my $clock = shift;
	my $details = shift;

	wrn(uc($service), " service availability result is missing for timestamp ", ts_str($clock), " ($clock).".
		" This means for that period the SLV availability script ($avail_key) was not run.".
		" This may happen e. g. if cron was not running at some point. In order to fix this problem".
		" please run the following script:".
		"\n  $avail_key.pl --from $clock");
}

# returns hash reference of Probe=>itemid of specified key (e. g. 'rsm.dns.udp[{$RSM.TLD}]')
#
# {
#    'Amsterdam' => 'itemid1',
#    'London' => 'itemid2',
#    ...
# }
sub get_service_status_itemids
{
	my $tld = shift;
	my $key = shift;

	my $key_condition = (substr($key, -1) eq ']' ? "i.key_='$key'" : "i.key_ like '$key%'");

	my $sql =
		"select h.host,i.itemid".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and i.status<>".ITEM_STATUS_DISABLED.
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

# Get test results from the probes. The result is unsigned integer:
#
# - DNS test	: number of working Name Servers
# - RDDS test	: 3 (only RDDS80), 2 (only RDDS43), 1 (Up) or 0 (Down)
# - EPP test	: 1 (Up) or 0 (Down)
#
# Result is formatted the following way:
#
# {
#     'Probe1' =>
#     [
#         {
#             'clock' => 1234234234,
#             'value' => 1
#         },
#         {
#             'clock' => 1234234294,
#             'value' => 1
#         }
#     ],
#     'Probe2' =>
#     [
#         {
#             'clock' => 1234234234,
#             'value' => 0
#         },
#         {
#             'clock' => 1234234294,
#             'value' => 1
#         }
#     ]
# }
#
sub get_probe_results
{
	my $itemids_ref = shift;
	my $from = shift;
	my $till = shift;

	my %result;

	# generate list if itemids
	my $itemids_str = '';
	foreach my $probe (keys(%$itemids_ref))
	{
		$itemids_str .= ',' unless ($itemids_str eq '');
		$itemids_str .= $itemids_ref->{$probe};
	}

	if ($itemids_str ne '')
	{
		my $rows_ref = db_select("select itemid,value,clock from history_uint where itemid in ($itemids_str) and " . sql_time_condition($from, $till). " order by clock");

		foreach my $row_ref (@$rows_ref)
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

sub get_ip_version
{
	return 6 if (shift() =~ /:/);

	return 4;
}

sub __make_incident
{
	my %h;

	$h{'eventid'} = shift;
	$h{'false_positive'} = shift;
	$h{'start'} = shift;
	$h{'end'} = shift;

	return \%h;
}

sub sql_time_condition
{
	my $from = shift;
	my $till = shift;

	if (defined($from) and not defined($till))
	{
		return "clock>=$from";
	}

	if (not defined($from) and defined($till))
	{
		return "clock<=$till";
	}

	if (defined($from) and defined($till))
	{
		return "clock=$from" if ($from == $till);
		fail("invalid time conditions: from=$from till=$till") if ($from > $till);
		return "clock between $from and $till";
	}

	return "1=1";
}

# return incidents as an array reference (sorted by time):
#
# [
#     {
#         'eventid' => '5881',
#         'start' => '1418272230',
#         'end' => '1418273230',
#         'false_positive' => '0'
#     },
#     {
#         'eventid' => '6585',
#         'start' => '1418280000',
#         'false_positive' => '1'
#     }
# ]
#
# An incident is a period when the problem was active. This period is
# limited by 2 events, the PROBLEM event and the first OK event after
# that.
#
# Incidents are returned within time limits specified by $from and $till.
# If an incident is on-going at the $from time the event "start" time is
# used. In case event is on-going at time specified as $till it's "end"
# time is not defined.
sub get_incidents
{
	my $itemid = shift;
	my $from = shift;
	my $till = shift;

	my (@incidents, $rows_ref, $row_ref);

	$rows_ref = db_select(
		"select distinct t.triggerid".
		" from triggers t,functions f".
		" where t.triggerid=f.triggerid".
			" and t.status<>".TRIGGER_STATUS_DISABLED.
			" and f.itemid=$itemid".
			" and t.priority=".TRIGGER_SEVERITY_NOT_CLASSIFIED);

	my $rows = scalar(@$rows_ref);

	unless ($rows == 1)
	{
		wrn("configuration error: item $itemid must have one not classified trigger (found: $rows)");
		return \@incidents;
	}

	my $triggerid = $rows_ref->[0]->[0];

	my $last_trigger_value = TRIGGER_VALUE_FALSE;

	if (defined($from))
	{
		# first check for ongoing incident
		$rows_ref = db_select(
			"select max(clock)".
			" from events".
			" where object=".EVENT_OBJECT_TRIGGER.
				" and source=".EVENT_SOURCE_TRIGGERS.
				" and objectid=$triggerid".
				" and clock<$from");

		$row_ref = $rows_ref->[0];

		if (defined($row_ref) and defined($row_ref->[0]))
		{
			my $preincident_clock = $row_ref->[0];

			$rows_ref = db_select(
				"select eventid,clock,value,false_positive".
				" from events".
				" where object=".EVENT_OBJECT_TRIGGER.
					" and source=".EVENT_SOURCE_TRIGGERS.
					" and objectid=$triggerid".
					" and clock=$preincident_clock".
				" order by ns desc".
				" limit 1");

			$row_ref = $rows_ref->[0];

			my $eventid = $row_ref->[0];
			my $clock = $row_ref->[1];
			my $value = $row_ref->[2];
			my $false_positive = $row_ref->[3];

			dbg("reading pre-event $eventid: clock:" . ts_str($clock) . " ($clock), value:", ($value == 0 ? 'OK' : 'PROBLEM'), ", false_positive:$false_positive") if (opt('debug'));

			# do not add 'value=TRIGGER_VALUE_TRUE' to SQL above just for corner case of 2 events at the same second
			if ($value == TRIGGER_VALUE_TRUE)
			{
				push(@incidents, __make_incident($eventid, $false_positive, $clock));

				$last_trigger_value = TRIGGER_VALUE_TRUE;
			}
		}
	}

	# now check for incidents within given period
	$rows_ref = db_select(
		"select eventid,clock,value,false_positive".
		" from events".
		" where object=".EVENT_OBJECT_TRIGGER.
			" and source=".EVENT_SOURCE_TRIGGERS.
			" and objectid=$triggerid".
			" and ".sql_time_condition($from, $till).
		" order by clock,ns");

	foreach my $row_ref (@$rows_ref)
	{
		my $eventid = $row_ref->[0];
		my $clock = $row_ref->[1];
		my $value = $row_ref->[2];
		my $false_positive = $row_ref->[3];

		dbg("reading event $eventid: clock:" . ts_str($clock) . " ($clock), value:", ($value == 0 ? 'OK' : 'PROBLEM'), ", false_positive:$false_positive") if (opt('debug'));

		# ignore non-resolved false_positive incidents (corner case)
		if ($value == TRIGGER_VALUE_TRUE && $last_trigger_value == TRIGGER_VALUE_TRUE)
		{
			my $idx = scalar(@incidents) - 1;

			if ($incidents[$idx]->{'false_positive'} != 0)
			{
				# replace with current
				$incidents[$idx]->{'eventid'} = $eventid;
				$incidents[$idx]->{'false_positive'} = $false_positive;
				$incidents[$idx]->{'start'} = $clock;
			}
		}

		next if ($value == $last_trigger_value);

		if ($value == TRIGGER_VALUE_FALSE)
		{
			# event that closes the incident
			my $idx = scalar(@incidents) - 1;

			$incidents[$idx]->{'end'} = $clock;
		}
		else
		{
			# event that starts an incident
			push(@incidents, __make_incident($eventid, $false_positive, $clock));
		}

		$last_trigger_value = $value;
	}

	# DEBUG
	if (opt('debug'))
	{
		foreach (@incidents)
		{
			my $eventid = $_->{'eventid'};
			my $inc_from = $_->{'start'};
			my $inc_till = $_->{'end'};
			my $false_positive = $_->{'false_positive'};

			if (opt('debug'))
			{
				my $str = "$eventid";
				$str .= " (false positive)" if ($false_positive != 0);
				$str .= ": " . ts_str($inc_from) . " ($inc_from) -> ";
				$str .= $inc_till ? ts_str($inc_till) . " ($inc_till)" : "null";

				dbg($str);
			}
		}
	}

	return \@incidents;
}

sub get_incidents2
{
	my $itemid = shift;
	my $delay = shift;
	my $from = shift;
	my $till = shift;

	my (@incidents, $rows_ref, $row_ref);

	$rows_ref = db_select(
		"select distinct t.triggerid".
		" from triggers t,functions f".
		" where t.triggerid=f.triggerid".
			" and t.status<>".TRIGGER_STATUS_DISABLED.
			" and f.itemid=$itemid".
			" and t.priority=".TRIGGER_SEVERITY_NOT_CLASSIFIED);

	my $rows = scalar(@$rows_ref);

	unless ($rows == 1)
	{
		wrn("configuration error: item $itemid must have one not classified trigger (found: $rows)");
		return \@incidents;
	}

	my $triggerid = $rows_ref->[0]->[0];

	my $last_trigger_value = TRIGGER_VALUE_FALSE;

	if (defined($from))
	{
		# First check for ongoing incident.

		my $attempts = 5;

		undef($row_ref);

		my $attempt = 0;

		my $clock_till = $from;
		my $clock_from = $clock_till - SEC_PER_WEEK;
		$clock_till--;

		while ($attempt++ < $attempts && !defined($row_ref))
		{
			$rows_ref = db_select(
				"select max(clock)".
				" from events".
				" where object=".EVENT_OBJECT_TRIGGER.
					" and source=".EVENT_SOURCE_TRIGGERS.
					" and objectid=$triggerid".
					" and " . sql_time_condition($clock_from, $clock_till));

			$row_ref = $rows_ref->[0];

			$clock_till = $clock_from - 1;
			$clock_from -= (SEC_PER_WEEK * $attempt * 2);
		}

		if (!defined($row_ref))
		{
			$rows_ref = db_select(
				"select max(clock)".
				" from events".
				" where object=".EVENT_OBJECT_TRIGGER.
					" and source=".EVENT_SOURCE_TRIGGERS.
					" and objectid=$triggerid".
					" and clock<$clock_from");

			$row_ref = $rows_ref->[0];
		}

		if (defined($row_ref) and defined($row_ref->[0]))
		{
			my $preincident_clock = $row_ref->[0];

			$rows_ref = db_select(
				"select eventid,clock,value,false_positive".
				" from events".
				" where object=".EVENT_OBJECT_TRIGGER.
					" and source=".EVENT_SOURCE_TRIGGERS.
					" and objectid=$triggerid".
					" and clock=$preincident_clock".
				" order by ns desc".
				" limit 1");

			$row_ref = $rows_ref->[0];

			my $eventid = $row_ref->[0];
			my $clock = $row_ref->[1];
			my $value = $row_ref->[2];
			my $false_positive = $row_ref->[3];

			dbg("reading pre-event $eventid: clock:" . ts_str($clock) . " ($clock), value:", ($value == 0 ? 'OK' : 'PROBLEM'), ", false_positive:$false_positive") if (opt('debug'));

			# do not add 'value=TRIGGER_VALUE_TRUE' to SQL above just for corner case of 2 events at the same second
			if ($value == TRIGGER_VALUE_TRUE)
			{
				push(@incidents, __make_incident($eventid, $false_positive, cycle_start($clock, $delay)));

				$last_trigger_value = TRIGGER_VALUE_TRUE;
			}
		}
	}

	# now check for incidents within given period
	$rows_ref = db_select(
		"select eventid,clock,value,false_positive".
		" from events".
		" where object=".EVENT_OBJECT_TRIGGER.
			" and source=".EVENT_SOURCE_TRIGGERS.
			" and objectid=$triggerid".
			" and ".sql_time_condition($from, $till).
		" order by clock,ns");

	foreach my $row_ref (@$rows_ref)
	{
		my $eventid = $row_ref->[0];
		my $clock = $row_ref->[1];
		my $value = $row_ref->[2];
		my $false_positive = $row_ref->[3];

		dbg("reading event $eventid: clock:" . ts_str($clock) . " ($clock), value:", ($value == 0 ? 'OK' : 'PROBLEM'), ", false_positive:$false_positive") if (opt('debug'));

		# ignore non-resolved false_positive incidents (corner case)
		if ($value == TRIGGER_VALUE_TRUE && $last_trigger_value == TRIGGER_VALUE_TRUE)
		{
			my $idx = scalar(@incidents) - 1;

			if ($incidents[$idx]->{'false_positive'} != 0)
			{
				# replace with current
				$incidents[$idx]->{'eventid'} = $eventid;
				$incidents[$idx]->{'false_positive'} = $false_positive;
				$incidents[$idx]->{'start'} = cycle_start($clock, $delay);
			}
		}

		next if ($value == $last_trigger_value);

		if ($value == TRIGGER_VALUE_FALSE)
		{
			# event that closes the incident
			my $idx = scalar(@incidents) - 1;

			$incidents[$idx]->{'end'} = cycle_end($clock, $delay);
		}
		else
		{
			# event that starts an incident
			push(@incidents, __make_incident($eventid, $false_positive, cycle_start($clock, $delay)));
		}

		$last_trigger_value = $value;
	}

	# DEBUG
	if (opt('debug'))
	{
		foreach (@incidents)
		{
			my $eventid = $_->{'eventid'};
			my $inc_from = $_->{'start'};
			my $inc_till = $_->{'end'};
			my $false_positive = $_->{'false_positive'};

			if (opt('debug'))
			{
				my $str = "$eventid";
				$str .= " (false positive)" if ($false_positive != 0);
				$str .= ": " . ts_str($inc_from) . " ($inc_from) -> ";
				$str .= $inc_till ? ts_str($inc_till) . " ($inc_till)" : "null";

				dbg($str);
			}
		}
	}

	return \@incidents;
}

sub get_downtime
{
	my $itemid = shift;
	my $from = shift;
	my $till = shift;
	my $ignore_incidents = shift;	# if set check the whole period, otherwise only consider failed tests within incidents

	my $incidents;
	if ($ignore_incidents)
	{
		push(@$incidents, __make_incident(0, 0, $from, $till));
	}
	else
	{
		$incidents = get_incidents($itemid, $from, $till);
	}

	my $count = 0;

	my $total = scalar(@$incidents);
	my $downtime = 0;

	my $sec;
	if (opt('stats'))
	{
		$sec = time();
	}

	my $fetches = 0;

	foreach (@$incidents)
	{
		my $false_positive = $_->{'false_positive'};
		my $period_from = $_->{'start'};
		my $period_till = $_->{'end'};

		fail("internal error: incident outside time bounds, check function get_incidents()") if (($period_from < $from) and defined($period_till) and ($period_till < $from));

		$period_from = $from if ($period_from < $from);
		$period_till = $till unless (defined($period_till)); # last incident may be ongoing

		next if ($false_positive != 0);

		my $rows_ref = db_select(
			"select value,clock".
			" from history_uint".
			" where itemid=$itemid".
				" and clock between $period_from and $period_till".
			" order by clock");

		my $prevvalue = UP;
		my $prevclock = 0;

		foreach my $row_ref (@$rows_ref)
		{
			$fetches++;

			my $value = avail_up_down($row_ref->[0]);
			my $clock = $row_ref->[1];

			# In case of multiple values per second treat them as one. Up value prioritized.
			if ($prevclock == $clock)
			{
				# more than one value per second
				$prevvalue = UP if ($prevvalue == DOWN and $value == UP);
				next;
			}

			$downtime += $clock - $prevclock if ($prevvalue == DOWN);

			$prevvalue = $value;
			$prevclock = $clock;
		}

		# leftover of downtime
		$downtime += $period_till - $prevclock if ($prevvalue == DOWN);
	}

	$downtime /= 60;	# minutes;

	if (opt('stats'))
	{
		my $sec_cur = time() - $sec;
		$sql_time += $sec_cur;

		info(sprintf("down:%dm time:%.3fs fetches:%d", $downtime, $sec_cur, $fetches));
	}

	return $downtime;
}

sub get_downtime_prepare
{
	my $query =
		"select value,clock".
		" from history_uint".
		" where itemid=?".
			" and clock between ? and ?".
		" order by clock";

	my $sec;
	if (opt('stats'))
	{
		$sec = time();
	}

	my $sth = $dbh->prepare($query)
		or fail("cannot prepare [$query]: ", $dbh->errstr);

	if (opt('stats'))
	{
		$sql_time += time() - $sec;
	}

	dbg("[$query]");

	return $sth;
}

sub get_downtime_execute
{
	my $sth = shift;
	my $itemid = shift;
	my $from = shift;
	my $till = shift;
	my $ignore_incidents = shift;	# if set check the whole period, otherwise only consider failed tests within incidents

	my $incidents;
	if ($ignore_incidents)
	{
		push(@$incidents, __make_incident(0, 0, $from, $till));
	}
	else
	{
		$incidents = get_incidents($itemid, $from, $till);
	}

	my $count = 0;

	my $total = scalar(@$incidents);
	my $downtime = 0;

	my $sec;
	if (opt('stats'))
	{
		$sec = time();
	}

	my $fetches = 0;

	foreach (@$incidents)
	{
		my $false_positive = $_->{'false_positive'};
		my $period_from = $_->{'start'};
		my $period_till = $_->{'end'};

		fail("internal error: incident outside time bounds, check function get_incidents()") if (($period_from < $from) and defined($period_till) and ($period_till < $from));

		$period_from = $from if ($period_from < $from);
		$period_till = $till unless (defined($period_till)); # last incident may be ongoing

		next if ($false_positive != 0);

		$sth->bind_param(1, $itemid);
		$sth->bind_param(2, $period_from);
		$sth->bind_param(3, $period_till);

		$sth->execute()
			or fail("cannot execute query: ", $sth->errstr);

		my ($value, $clock);
		$sth->bind_columns(\$value, \$clock);

		my $prevvalue = UP;
		my $prevclock = 0;

		while ($sth->fetch)
		{
			$value = avail_up_down($value);

			$fetches++;

			# In case of multiple values per second treat them as one. Up value prioritized.
			if ($prevclock == $clock)
			{
				# more than one value per second
				$prevvalue = UP if ($prevvalue == DOWN and $value == UP);
				next;
			}

			$downtime += $clock - $prevclock if ($prevvalue == DOWN);

			$prevvalue = $value;
			$prevclock = $clock;
		}

		# leftover of downtime
		$downtime += $period_till - $prevclock if ($prevvalue == DOWN);

		$sth->finish();
		$sql_count++;
	}

	$downtime /= 60;	# minutes;

	if (opt('stats'))
	{
		my $sec_cur = time() - $sec;
		$sql_time += $sec_cur;

		info(sprintf("down:%dm time:%.3fs fetches:%d", $downtime, $sec_cur, $fetches));
	}

	return $downtime;
}

sub avail_result_msg
{
	my $test_result = shift;
	my $success_values = shift;
	my $total_results = shift;
	my $perc = shift;

	my $result_str;

	if ($test_result == UP)
	{
		$result_str = "Up";
	}
	elsif ($test_result == DOWN)
	{
		$result_str = "Down";
	}
	elsif ($test_result == UP_INCONCLUSIVE)
	{
		$result_str = "UP (inconclusive)";
	}
	else
	{
		$result_str = "*UNKNOWN*";
	}

	return sprintf("$result_str (%d/%d positive, %.3f%%)", $success_values, $total_results, $perc);
}

#
# returns:
# E_FAIL      - no such item in database
# otherwise - lastvalue (undef if lastvalue == NULL)
sub get_current_value
{
	my $itemid = shift;

	my $rows_ref = db_select("select lastvalue from items where itemid=$itemid");

	return E_FAIL if (scalar(@$rows_ref) == 0);

	# undef in case lastvalue=NULL
	return $rows_ref->[0]->[0];
}

#
# returns array of itemids: [itemid1, itemid2 ...]
#
sub get_itemids_by_hostids
{
	my $hostids_ref = shift;
	my $all_items = shift;

	my @result = ();

	foreach my $hostid (@$hostids_ref)
	{
		unless ($all_items->{$hostid})
		{
			dbg("\nhostid $hostid from:\n", Dumper($hostids_ref), "was not found in:\n", Dumper($all_items)) if (opt('debug'));
			fail("internal error: no hostid $hostid in input items");
		}

		foreach my $itemid (keys(%{$all_items->{$hostid}}))
		{
			push(@result, $itemid);
		}
	}

	return \@result;
}

# organize values from all probes grouped by nsip and return "nsip"->itemid->clock->value hash:
#
# {
#     'ns1,192.0.34.201' =>
#     {
#         23764 =>
#         {
#             1418273230 => '-204.0000',
#             1418273290 => '547',
# ...
sub __get_nsip_values
{
	my $itemids_ref = shift;
	my $times_ref = shift; # from, till, ...
	my $items_ref = shift;

	my $result;

	if (scalar(@$itemids_ref) != 0)
	{
		my $itemids_str = "";
		foreach my $itemid (@$itemids_ref)
		{
			$itemids_str .= "," unless ($itemids_str eq "");
			$itemids_str .= $itemid;
		}

		my $idx = 0;
		my $times_count = scalar(@$times_ref);
		while ($idx < $times_count)
		{
			my $from = $times_ref->[$idx++];
			my $till = $times_ref->[$idx++];

			my $rows_ref = db_select(
				"select itemid,value,clock".
				" from history".
				" where itemid in ($itemids_str)".
					" and " . sql_time_condition($from, $till));

			foreach my $row_ref (@$rows_ref)
			{
				my $itemid = $row_ref->[0];
				my $value = $row_ref->[1];
				my $clock = $row_ref->[2];

				my $nsip;
				my $last = 0;
				foreach my $hostid (keys(%$items_ref))
				{
					foreach my $i (keys(%{$items_ref->{$hostid}}))
					{
						if ($i == $itemid)
						{
							$nsip = $items_ref->{$hostid}{$i};
							$last = 1;
							last;
						}
					}
					last if ($last == 1);
				}

				fail("internal error: name server of item $itemid not found") unless (defined($nsip));

				if (!defined($result->{$nsip}) || !defined($result->{$nsip}->{$itemid}) ||
						!defined($result->{$nsip}->{$itemid}->{$clock}) ||
						($value >= 0 && $value < $result->{$nsip}->{$itemid}->{$clock}))
				{
					$result->{$nsip}->{$itemid}->{$clock} = $value;
				}
			}
		}
	}

	return $result;
}

sub __get_valuemappings
{
	my $vmname = shift;

	my $rows_ref = db_select("select m.value,m.newvalue from valuemaps v,mappings m where v.valuemapid=m.valuemapid and v.name='$vmname'");

	my $result;
	foreach my $row_ref (@$rows_ref)
	{
		$result->{$row_ref->[0]} = $row_ref->[1];
	}

	return $result;
}

sub get_valuemaps
{
	my $service = shift;

	my $vmname;
	if ($service eq 'dns' or $service eq 'dnssec')
	{
		$vmname = 'RSM DNS result';
	}
	elsif ($service eq 'rdds')
	{
		$vmname = 'RSM RDDS result';
	}
	elsif ($service eq 'epp')
	{
		$vmname = 'RSM EPP availability';
	}
	else
	{
		fail("service '$service' is unknown");
	}

	return __get_valuemappings($vmname);
}

sub get_statusmaps
{
	my $service = shift;

	my $vmname;
	if ($service eq 'dns' or $service eq 'dnssec' or $service eq 'epp')
	{
		$vmname = 'RSM DNS availability';
	}
	elsif ($service eq 'rdds')
	{
		$vmname = 'RSM RDDS availability';
	}
	else
	{
		fail("service '$service' is unknown");
	}

	return __get_valuemappings($vmname);
}

sub get_detailed_result
{
	my $maps = shift;
	my $value = shift;

	return undef unless($value);

	my $value_int = int($value);

	return $value_int unless (exists($maps->{$value_int}));

	return "$value_int, " . $maps->{$value_int};
}

sub get_result_string
{
	my $maps = shift;
	my $value = shift;

	my $value_int = int($value);

	return $value_int unless (exists($maps->{$value_int}));

	return $maps->{$value_int};
}

# returns (tld, service)
sub get_tld_by_trigger
{
	my $triggerid = shift;

	my $rows_ref = db_select("select distinct itemid from functions where triggerid=$triggerid");

	my $itemid = $rows_ref->[0]->[0];

	fail("cannot get item by triggerid $triggerid") unless ($itemid);

	dbg("itemid:$itemid");

	$rows_ref = db_select("select hostid,substring(key_,9,locate('.avail',key_)-9) as service from items where itemid=$itemid");

	my $hostid = $rows_ref->[0]->[0];
	my $service = $rows_ref->[0]->[1];

	fail("cannot get TLD by itemid $itemid") unless ($hostid);

	dbg("hostid:$hostid");

	$rows_ref = db_select("select host from hosts where hostid=$hostid");

	return ($rows_ref->[0]->[0], $service);
}

# beginning of given minute
sub truncate_from
{
	my $ts = shift;

	return $ts - ($ts % 60);
}

# last second of given minute
sub truncate_till
{
	my $ts = shift;

	return $ts + (60 - ($ts % 60) - 1);
}

# value timestamp if the cycle
sub get_value_ts
{
	my $till = shift;	# end of the period

	return $till - VALUE_TS_SHIFT;
}

# whether additional alerts through Redis are enabled, disable in config passed with set_slv_config()
sub alerts_enabled
{
	return SUCCESS if ($config && $config->{'redis'} && $config->{'redis'}->{'enabled'} && ($config->{'redis'}->{'enabled'} ne "0"));

	return E_FAIL;
}

sub is_from_valid
{
	return SUCCESS if ((shift() % 60) == 0);

	return E_FAIL;
}

sub is_till_valid
{
	return SUCCESS if ((shift() % 60) == 59);

	return E_FAIL;
}

# returns beginning of the test period if specified upper bound is within it,
# 0 otherwise
sub get_test_start_time
{
	my $till = shift;	# must be :59 seconds
	my $delay = shift;	# service delay in seconds (e. g. DNS: 60)

	fail("internal error: invalid upper bound of time period specified: ", ts_full($till), ", expected last second of the minute")
		if (is_till_valid($till) != SUCCESS);

	$till++;

	my $remainder = $till % $delay;

	return 0 if ($remainder != 0);

	return $till - $delay;
}

# returns UP or DOWN
sub avail_up_down
{
	my $avail_value = shift;

	if ($avail_value == UP_INCONCLUSIVE)
	{
		$avail_value = UP;
	}

	return $avail_value;
}

# $services is a hash reference of services that need to be checked.
# For each service the delay must be provided. "from" and "till" values
# will be set for services whose tests fall under given time between
# $check_from and $check_till.
#
# Input:
#
# [
#   {'dns' => 60},
#   {'rdds' => 300}
# ]
#
# Output:
#
# [
#   {'dns' => 60, 'from' => 1234234200, 'till' => 1234234259},	# <- test period found
#   {'rdds' => 300}						# <- test period not found
# ]
#
# The return value is ARRAY of 2 members: min ($from) and max ($till) from all found periods.
#
sub get_real_services_period
{
	my $services = shift;
	my $check_from = shift;
	my $check_till = shift;

	my ($from, $till);

	# adjust test and probe periods we need to calculate for
	foreach my $service (keys(%$services))
	{
		my $delay = $services->{$service}{'delay'};

		my ($loop_from, $loop_till);

		# go through the check period minute by minute selecting test cycles
		for ($loop_from = $check_from, $loop_till = $loop_from + 59; $loop_from < $check_till; $loop_from += 60, $loop_till += 60)
		{
			my $test_from = get_test_start_time($loop_till, $delay);

			if ($test_from != 0)
			{
				if (!$from || $from > $test_from)
				{
					$from = $test_from;
				}

				if (!$till || $till < $loop_till)
				{
					$till = $loop_till;
				}

				if (!$services->{$service}{'from'})
				{
					$services->{$service}{'from'} = $test_from;
				}

				if (!$services->{$service}{'till'} || $services->{$service}{'till'} < $loop_till)
				{
					$services->{$service}{'till'} = $loop_till;
				}
			}
		}
	}

	return ($from, $till);
}

sub format_stats_time
{
	my $time = shift;

	my $m = int($time / 60);
	my $s = $time - $m * 60;

	return sprintf("%dm %ds", $m, $s) if ($m != 0);

	return sprintf("%.3lfs", $s);
}

sub friendly_delay
{
	my $delay = shift;

	my $m = int($delay / 60);
	my $s = $delay - $m * 60;

	if ($m)
	{
		return sprintf("%dm %ds", $m, $s) if ($s);
		return sprintf("%dm", $m, $s);
	}

	return sprintf("%ds", $s);
}

sub slv_exit
{
	my $rv = shift;

	if (SUCCESS == $rv && opt('stats'))
	{
		my $prefix = $tld ? "$tld " : '';

		my $sql_str = format_stats_time($sql_time);

		$sql_str .= " ($sql_count queries)";

		my $total_str = format_stats_time(time() - $start_time);

		print($prefix, "total     : $total_str\n");
		print($prefix, "sql       : $sql_str\n");

		if ($lock_count > 0)
		{
			my $l_time = format_stats_time($lock_time);
			my $l_w_time = format_stats_time($lock_wait_time);

			print($prefix, "locks     : $l_time ($lock_count)\n");
			print($prefix, "lock wait : $l_w_time\n");
		}
	}

	exit($rv);
}

sub slv_stats_reset
{
	$start_time = time();
	$sql_time = 0.0;
	$sql_count = 0;
	$lock_time = 0.0;
	$lock_wait_time = 0.0;
	$lock_count = 0;
}

sub slv_lock
{
	#printf("%7d: %s\n", $$, 'TRY');

	my $tmp;
	if (opt('stats'))
	{
		$tmp = time();
	}

        open($_lock_fh, ">", _LOCK_FILE) or fail("cannot open lock file " . _LOCK_FILE . ": $!");

	flock($_lock_fh, LOCK_EX) or fail("cannot lock using file " . _LOCK_FILE . ": $!");

	if (opt('stats'))
	{
		$lock_wait_time += time() - $tmp;

		$lock_tmp = time();
		$lock_count++;
	}

	#printf("%7d: %s\n", $$, 'LOCK');
}

sub slv_unlock
{
	close($_lock_fh) or fail("cannot close lock file " . _LOCK_FILE . ": $!");

	if (opt('stats'))
	{
		$lock_time += time() - $lock_tmp;
	}

	#printf("%7d: %s\n", $$, 'UNLOCK');
}

sub exit_if_running
{
	return if (opt('dry-run'));

	my $filename = __get_pidfile();

	my $pidfile = File::Pid->new({ file => $filename });
	fail("cannot lock script") unless (defined($pidfile));

	$pidfile->write() or fail("cannot write to a pid file ", $pidfile->file);

	return if ($pidfile->pid == $$);

	# pid file exists and has valid pid
	my $pid = $pidfile->running();
	fail("already running (pid:$pid)") if ($pid);

	$pidfile->pid($$);
	$pidfile->write() or fail("cannot write to a pid file ", $pidfile->file);
}

sub dbg
{
	return unless (opt('debug'));

	__log('debug', join('', @_));
}

sub info
{
	__log('info', join('', @_));
}

sub wrn
{
	__log('warning', join('', @_));
}

sub fail
{
	__log('err', join('', @_));

	slv_exit(E_FAIL);
}

sub trim
{
	my $out = shift;

	$out =~ s/^\s+//;
	$out =~ s/\s+$//;

	return $out;
}

sub parse_opts
{
	if (!GetOptions(\%OPTS, 'now=i', 'help!', 'dry-run!', 'warnslow=f', 'nolog!', 'debug!', 'stats!', @_))
	{
		pod2usage(-verbose => 0, -input => $POD2USAGE_FILE);
	}

	if (opt('help'))
	{
		pod2usage(-verbose => 1, -input => $POD2USAGE_FILE);
	}

	setopt('nolog') if (opt('dry-run') || opt('debug'));

	$start_time = time() if (opt('stats'));

	if (opt('debug'))
	{
		foreach my $opt (keys(%OPTS))
		{
			dbg("$opt\t: ", $OPTS{$opt});
		}
	}
}

sub parse_avail_opts
{
	$POD2USAGE_FILE = '/opt/zabbix/scripts/slv/rsm.slv.avail.usage';

	parse_opts('tld=s', 'cycles=n', @_);
}

sub parse_rollweek_opts
{
	$POD2USAGE_FILE = '/opt/zabbix/scripts/slv/rsm.slv.rollweek.usage';

	parse_opts('tld=s', , @_);
}

sub opt
{
	my $key = shift;

	return defined($OPTS{$key});
}

sub getopt
{
	my $key = shift;

	return $OPTS{$key};
}

sub setopt
{
	my $key = shift;
	my $value = shift;

	$value = 1 unless (defined($value));

	$OPTS{$key} = $value;
}

sub optkeys
{
	return keys(%OPTS);
}

sub ts_str
{
	my $ts = shift || time();

	my ($sec, $min, $hour, $mday, $mon, $year, $wday, $yday, $isdst) = localtime($ts);

	$year += 1900;
	$mon++;

	return sprintf("%4.2d/%2.2d/%2.2d %2.2d:%2.2d:%2.2d", $year, $mon, $mday, $hour, $min, $sec);
}

sub ts_full
{
	my $ts = shift;

	if ($ts)
	{
		return ts_str($ts) . " ($ts)";
	}

	return "(null)";
}

sub selected_period
{
	my $from = shift;
	my $till = shift;

	return "till " . ts_str($till) if (!$from and $till);
	return "from " . ts_str($from) if ($from and !$till);
	return "from " . ts_str($from) . " till " . ts_str($till) if ($from and $till);

	return "any time";
}

sub write_file
{
	my $full_path = shift;
	my $text = shift;

	my $OUTFILE;

	return E_FAIL unless (open($OUTFILE, '>', $full_path));

	my $rv = print { $OUTFILE } $text;

	close($OUTFILE);

	return E_FAIL unless ($rv);

	return SUCCESS;
}

sub cycle_start
{
	my $now = shift;
	my $delay = shift;

	return $now - ($now % $delay);
}

sub cycle_end
{
	my $now = shift;
	my $delay = shift;

	return $now + $delay - ($now % $delay) - 1;
}

sub get_default_period
{
	my $now = shift;
	my $delay = shift;
	my $from = shift;
	my $till = shift;

	if (!defined($from) && !defined($till))
	{
		# current month, if nothing specified
		$from = get_month_from($now);
		$till = cycle_end($now, $delay);

		return ($from, $till);
	}

	if (defined($from))
	{
		$from = cycle_start($from, $delay);
	}
	else
	{
		$from = get_month_from($till);
	}

	if (defined($till))
	{
		$till = cycle_end($till, $delay);
	}
	else
	{
		$till = cycle_end($now, $delay);
	}

	return ($from, $till);
}

sub rsm_slv_error
{
	return $__rsm_slv_error;
}

sub get_readable_tld
{
	my $tld = shift;

	return ROOT_ZONE_READABLE if ($tld eq ".");

	return $tld;
}

sub max
{
	$_[$_[0] < $_[1]];
}

sub usage
{
	pod2usage(shift);
}

#################
# Internal subs #
#################

my $program = $0; $program =~ s,.*/,,g;
my $logopt = 'pid';
my $facility = 'user';
my $prev_tld = "";
my $log_open = 0;

sub __func
{
	my $depth = 4;

	my $func = (caller($depth))[3];

	$func =~ s/^[^:]*::(.*)$/$1/ if (defined($func));

	return "$func() " if (defined($func));

	return "";
}

sub __log
{
	my $syslog_priority = shift;
	my $msg = shift;

	my $priority;
	my $stdout = 1;

	if ($syslog_priority eq 'info')
	{
		$priority = 'INF';
	}
	elsif ($syslog_priority eq 'err')
	{
		$stdout = 0;
		$priority = 'ERR';
	}
	elsif ($syslog_priority eq 'warning')
	{
		$stdout = 0;
		$priority = 'WRN';
	}
	elsif ($syslog_priority eq 'debug')
	{
		$priority = 'DBG';
	}
	else
	{
		$priority = 'UND';
	}

	my $cur_tld = ($tld ? "$tld: " : "");

	if (opt('dry-run') or opt('nolog'))
	{
		print {$stdout ? *STDOUT : *STDERR} (ts_str(), " [$priority] $cur_tld", __func(), "$msg\n");
		return;
	}

	$cur_tld = ($tld ? "$tld-" : "");

	my $ident = $cur_tld . $program;

	if ($log_open == 0)
	{
		openlog($ident, $logopt, $facility);
		$log_open = 1;
	}
	elsif ($cur_tld ne $prev_tld)
	{
		closelog();
		openlog($ident, $logopt, $facility);
	}

	syslog($syslog_priority, ts_str() . " [$priority] $msg"); # first parameter is not used in our rsyslog template

	$prev_tld = $cur_tld;
}

sub __get_macro
{
	my $m = shift;

	my $rows_ref = db_select("select value from globalmacro where macro='$m'");

	fail("cannot find macro '$m'") unless (1 == scalar(@$rows_ref));

	return $rows_ref->[0]->[0];
}

sub __get_host_macro
{
	my $hostid = shift;
	my $m = shift;

	my $rows_ref = db_select("select value from hostmacro where hostid=$hostid and macro='$m'");

	fail("cannot find macro '$m'") unless (1 == scalar(@$rows_ref));

	return $rows_ref->[0]->[0];
}

# return an array reference of values of items for the particular period
sub __get_dbl_values
{
	my $itemids_ref = shift;
	my $from = shift;
	my $till = shift;

	my @result;

	if (0 != scalar(@$itemids_ref))
	{
		my $itemids_str = "";
		foreach my $itemid (@$itemids_ref)
		{
			$itemids_str .= "," unless ($itemids_str eq "");
			$itemids_str .= $itemid;
		}

		my $rows_ref = db_select("select value from history where itemid in ($itemids_str) and clock between $from and $till order by clock");

		foreach my $row_ref (@$rows_ref)
		{
			push(@result, $row_ref->[0]);
		}
	}

	return \@result;
}

sub __script
{
	my $script = $0;

	$script =~ s,.*/([^/]*)$,$1,;

	return $script;
}

sub __get_pidfile
{
	return PID_DIR . '/' . __script() . '.pid';
}

# Times when probe "lastaccess" within $probe_avail_limit.
sub __get_lastaccess_times
{
	my $itemid = shift;
	my $probe_avail_limit = shift;
	my $from = shift;
	my $till = shift;

	my ($rows_ref, @times, $last_status);

	# get the previous status
	$rows_ref = db_select(
		"select clock,value".
		" from history_uint".
		" where itemid=$itemid".
			" and clock between ".($from-3600)." and ".($from-1).
		" order by itemid desc,clock desc".
		" limit 1");

	$last_status = UP;
	if (scalar(@$rows_ref) != 0)
	{
		my $clock = $rows_ref->[0]->[0];
		my $value = $rows_ref->[0]->[1];

		dbg("clock:$clock value:$value");

		$last_status = DOWN if ($clock - $value > $probe_avail_limit);
	}

	push(@times, truncate_from($from)) if ($last_status == UP);

	$rows_ref = db_select(
		"select clock,value".
		" from history_uint".
		" where itemid=$itemid".
	    		" and clock between $from and $till".
	    		" and value!=0".
		" order by itemid,clock");

	foreach my $row_ref (@$rows_ref)
	{
		my $clock = $row_ref->[0];
		my $value = $row_ref->[1];

		my $status = ($clock - $value > $probe_avail_limit) ? DOWN : UP;

		if ($last_status != $status)
		{
			if ($status == UP)
			{
				$clock = truncate_from($clock);
			}
			else
			{
				$clock = truncate_till($clock);
			}

			push(@times, $clock);

			dbg("clock:$clock diff:", ($clock - $value));

			$last_status = $status;
		}
	}

	# push "till" to @times if it contains odd number of elements
	if (scalar(@times) != 0)
	{
		push(@times, $till) if ($last_status == UP);
	}

	return \@times;
}

sub __get_probestatus_times
{
	my $probe = shift;
	my $hostid = shift;
	my $times_ref = shift; # input
	my $key = shift;

	my ($rows_ref, @times, $last_status);

	my $key_match = "i.key_";
	$key_match .= ($key =~ m/%/) ? " like '$key'" : "='$key'";

	my $itemid;
	if ($key =~ m/%/)
	{
		$itemid = get_itemid_like_by_hostid($hostid, $key);
	}
	else
	{
		$itemid = get_itemid_by_hostid($hostid, $key);
	}

	if (!$itemid)
	{
		wrn("configuration error: ", rsm_slv_error());
		return;
	}

	$rows_ref = db_select(
		"select value".
		" from history_uint".
		" where itemid=$itemid".
			" and clock<" . $times_ref->[0].
		" order by clock desc".
		" limit 1");

	$last_status = UP;
	if (scalar(@$rows_ref) != 0)
	{
		my $value = $rows_ref->[0]->[0];

		$last_status = DOWN if ($value == OFFLINE);
	}

	my $idx = 0;
	my $times_count = scalar(@$times_ref);
	while ($idx < $times_count)
	{
		my $from = $times_ref->[$idx++];
		my $till = $times_ref->[$idx++];

		$rows_ref = db_select(
			"select clock,value".
			" from history_uint".
			" where itemid=$itemid".
				" and clock between $from and $till".
			" order by itemid,clock");

		push(@times, $from) if ($last_status == UP);

		foreach my $row_ref (@$rows_ref)
		{
			my $clock = $row_ref->[0];
			my $value = $row_ref->[1];

			my $status = ($value == OFFLINE) ? DOWN : UP;

			if ($last_status != $status)
			{
				push(@times, $clock);

				dbg("clock:$clock value:$value");

				$last_status = $status;
			}
		}

		# push "till" to @times if it contains odd number of elements
		if (scalar(@times) != 0)
		{
			push(@times, $till) if ($last_status == UP);
		}
	}

	return \@times;
}

sub __get_configvalue
{
	my $item_prefix = shift;
	my $item_param = shift;
	my $value_time = shift;

	my $hour = 3600;
	my $day = $hour * 24;
	my $month = $day * 30;

	my $diff = $hour;
	my $value = undef;

	my $key = "$item_prefix.configvalue[$item_param]";

	my $itemid = get_itemid_by_key($key);

	return unless ($itemid);

	while (not $value and $diff < $month)
	{
		my $rows_ref = db_select(
			"select value".
			" from history_uint".
			" where itemid=$itemid".
				" and clock between " . ($value_time - $diff) . " and $value_time".
			" order by clock desc".
			" limit 1");

		foreach my $row_ref (@$rows_ref)
		{
			$value = $row_ref->[0];
			last;
		}

		$diff = $day if ($diff == $hour);
		$diff = $month if ($diff == $day);
	}

	return $value;
}

sub __get_rsm_configvalue
{
	my $item_param = shift;
	my $value_time = shift;

	return __get_configvalue('rsm', $item_param, $value_time);
}

1;
