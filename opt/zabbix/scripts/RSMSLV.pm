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

use constant SUCCESS => 0;
use constant FAIL => 1;
use constant UP => 1;
use constant DOWN => 0;
use constant ONLINE => 1;
use constant OFFLINE => 0;
use constant SLV_UNAVAILABILITY_LIMIT => 49; # NB! must be in sync with frontend

use constant MAX_SERVICE_ERROR => -200; # -200, -201 ...
use constant RDDS_UP => 2; # results of input items: 0 - RDDS down, 1 - only RDDS43 up, 2 - both RDDS43 and RDDS80 up
use constant MIN_LOGIN_ERROR => -205;
use constant MAX_LOGIN_ERROR => -203;
use constant MIN_INFO_ERROR => -211;
use constant MAX_INFO_ERROR => -209;

use constant TRIGGER_SEVERITY_NOT_CLASSIFIED => 0;
use constant EVENT_OBJECT_TRIGGER => 0;
use constant EVENT_SOURCE_TRIGGERS => 0;
use constant TRIGGER_VALUE_FALSE => 0;
use constant TRIGGER_VALUE_TRUE => 1;
use constant INCIDENT_FALSE_POSITIVE => 1; # NB! must be in sync with frontend
use constant SENDER_BATCH_COUNT => 250;
use constant PROBE_LASTACCESS_ITEM => 'zabbix[proxy,{$RSM.PROXY_NAME},lastaccess]';
use constant PROBE_GROUP_NAME => 'Probes';
use constant PROBE_KEY_MANUAL => 'rsm.probe.status[manual]';
use constant PROBE_KEY_AUTOMATIC => 'rsm.probe.status[automatic,%]'; # match all in SQL

# In order to do the calculation we should wait till all the results
# are available on the server (from proxies). We shift back 2 minutes
# in case of "availability" and 3 minutes in case of "rolling week"
# calculations.
# NB! These numbers must be in sync with Frontend (details page)!
use constant AVAIL_SHIFT_BACK => 120; # seconds (must be divisible by 60 without remainder)
use constant ROLLWEEK_SHIFT_BACK => 180; # seconds (must be divisible by 60 without remainder)

our ($result, $dbh, $tld);

our %OPTS; # specified command-line options

our @EXPORT = qw($result $dbh $tld %OPTS
		SUCCESS FAIL UP DOWN RDDS_UP SLV_UNAVAILABILITY_LIMIT
		get_macro_minns get_macro_dns_probe_online get_macro_rdds_probe_online get_macro_dns_rollweek_sla
		get_macro_rdds_rollweek_sla get_macro_dns_udp_rtt_high get_macro_dns_udp_rtt_low
		get_macro_dns_tcp_rtt_low get_macro_rdds_rtt_low get_macro_dns_udp_delay get_macro_dns_tcp_delay
		get_macro_rdds_delay get_macro_epp_delay get_macro_epp_probe_online get_macro_epp_rollweek_sla
		get_macro_dns_update_time get_macro_rdds_update_time get_items_by_hostids get_tld_items
		get_macro_epp_rtt_low get_macro_probe_avail_limit get_item_data get_itemid_by_key get_itemid_by_host
		get_itemid_by_hostid get_itemid_like_by_hostid get_itemids get_lastclock get_tlds get_probes get_targets
		get_all_items get_all_ns_items tld_service_enabled db_connect db_select set_slv_config
		get_interval_bounds get_rollweek_bounds get_month_bounds get_curmon_bounds minutes_last_month
		get_online_probes get_probe_times probes2tldhostids init_values push_value send_values
		get_ns_from_key is_service_error process_slv_ns_monthly process_slv_avail process_slv_ns_avail
		process_slv_monthly get_results get_item_values check_lastclock get_downtime get_downtime_prepare
		get_downtime_execute avail_result_msg
		dbg info wrn fail slv_exit exit_if_running trim parse_opts ts_str usage);

# configuration, set in set_slv_config()
my $config = undef;

# whether additional alerts through Redis are enabled, disable in config passed with set_slv_config()
my $alerts_enabled = 1;

# make sure only one copy of script runs (unless in test mode)
my $pidfile;
use constant PID_DIR => '/tmp';

my @_sender_values; # used to send values to Zabbix server

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

sub get_macro_dns_udp_delay
{
    my $value_time = (shift or time() - AVAIL_SHIFT_BACK);

    my $item_param = 'RSM.DNS.UDP.DELAY';

    my $value = __get_rsm_configvalue($item_param, $value_time);

    return $value if ($value);

    return __get_macro('{$' . $item_param . '}');
}

sub get_macro_dns_tcp_delay
{
    my $value_time = (shift or time() - AVAIL_SHIFT_BACK);

    my $item_param = 'RSM.DNS.TCP.DELAY';

    my $value = __get_rsm_configvalue($item_param, $value_time);

    return $value if ($value);

    return __get_macro('{$' . $item_param . '}');
}

sub get_macro_rdds_delay
{
    my $value_time = (shift or time() - AVAIL_SHIFT_BACK);

    my $item_param = 'RSM.RDDS.DELAY';

    my $value = __get_rsm_configvalue($item_param, $value_time);

    return $value if ($value);

    return __get_macro('{$' . $item_param . '}');
}

sub get_macro_epp_delay
{
    my $value_time = (shift or time() - AVAIL_SHIFT_BACK);

    my $item_param = 'RSM.EPP.DELAY';

    my $value = __get_rsm_configvalue($item_param, $value_time);

    return $value if ($value);

    return __get_macro('{$' . $item_param . '}');
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
	    	" and h.host='$host'".
	    	" and (i.key_='$cfg_key_in' or i.key_ like '$cfg_key_out%')";
    }
    else
    {
	$sql =
	    "select i.key_,i.itemid,i.lastclock".
	    " from items i,hosts h".
	    " where i.hostid=h.hostid".
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

    my $rows_ref = db_select("select itemid from items where key_='$key'");

    fail("cannot find item ($key)") if (scalar(@$rows_ref) == 0);
    fail("more than one item ($key)") if (scalar(@$rows_ref) > 1);

    return $rows_ref->[0]->[0];
}

sub get_itemid_by_host
{
    my $host = shift;
    my $key = shift;

    my $rows_ref = db_select(
	"select i.itemid".
	" from items i,hosts h".
	" where i.hostid=h.hostid".
	    	" and h.host='$host'".
		" and i.key_='$key'");

    fail("cannot find item ($key) at host ($host)") if (scalar(@$rows_ref) == 0);
    fail("more than one item ($key) at host ($host)") if (scalar(@$rows_ref) > 1);

    return $rows_ref->[0]->[0];
}

sub get_itemid_by_hostid
{
    my $hostid = shift;
    my $key = shift;

    my $rows_ref = db_select("select itemid from items where hostid=$hostid and key_='$key'");

    fail("cannot find item ($key) at host (id:$hostid)") if (scalar(@$rows_ref) == 0);
    fail("more than one item ($key) at host (id:$hostid)") if (scalar(@$rows_ref) > 1);

    return $rows_ref->[0]->[0];
}

sub get_itemid_like_by_hostid
{
    my $hostid = shift;
    my $key = shift;

    my $rows_ref = db_select("select itemid from items where hostid=$hostid and key_ like '$key'");

    fail("cannot find item ($key) at host (id:$hostid)") if (scalar(@$rows_ref) == 0);
    fail("more than one item ($key) at host (id:$hostid)") if (scalar(@$rows_ref) > 1);

    return $rows_ref->[0]->[0];
}

sub get_itemids
{
    my $host = shift;
    my $key_part = shift;

    my $rows_ref = db_select(
	"select i.itemid,i.key_".
	" from items i,hosts h".
	" where i.hostid=h.hostid".
	    	" and h.host='$host'".
		" and i.key_ like '$key_part%'");

    fail("cannot find items ($key_part*) at host ($host)") if (scalar(@$rows_ref) == 0);

    my %result;

    foreach my $row_ref (@$rows_ref)
    {
	my $itemid = $row_ref->[0];
	my $key = $row_ref->[1];

	my $ns = get_ns_from_key($key);

	$result{$ns} = $itemid;
    }

    return \%result;
}

sub get_lastclock
{
    my $host = shift;
    my $key = shift;

    my $sql;

    if ("[" eq substr($key, -1))
    {
	$sql =
	    "select i.lastclock".
	    " from items i,hosts h".
	    " where i.hostid=h.hostid".
	    	" and h.host='$host'".
	    	" and i.key_ like '$key%'".
	    " limit 1";
    }
    else
    {
	$sql =
	    "select i.lastclock".
	    " from items i,hosts h".
	    " where i.hostid=h.hostid".
	    	" and h.host='$host'".
	    	" and i.key_='$key'".
	    " limit 1";
    }

    my $rows_ref = db_select($sql);

    fail("lastclock check failed: cannot find item ($key) at host ($host)") if (scalar(@$rows_ref) < 1);

    return $rows_ref->[0]->[0] ? $rows_ref->[0]->[0] : 0;
}

sub get_tlds
{
    my $service = shift;

    $service = defined($service) ? uc($service) : 'DNS';

    my $sql;

    if ($service eq 'DNS')
    {
	$sql =
	    "select h.host".
	    " from hosts h,hosts_groups hg,groups g".
	    " where h.hostid=hg.hostid".
		" and hg.groupid=g.groupid".
		" and g.name='TLDs'".
		" and h.status=0";
    }
    else
    {
	$sql =
	    "select h.host".
	    " from hosts h,hosts_groups hg,groups g,hosts h2,hostmacro hm".
	    " where h.hostid=hg.hostid".
	    	" and hg.groupid=g.groupid".
	    	" and h2.name=concat('Template ', h.host)".
	    	" and g.name='TLDs'".
	    	" and h2.hostid=hm.hostid".
	    	" and hm.macro='{\$RSM.TLD.$service.ENABLED}'".
	    	" and hm.value!=0".
	    	" and h.status=0";
    }

    $sql .= " order by h.host";

    my $rows_ref = db_select($sql);

    my @tlds;
    foreach my $row_ref (@$rows_ref)
    {
	push(@tlds, $row_ref->[0]);
    }

    return \@tlds;
}

# Returns a reference to hash of all probes (host => hostid).
sub get_probes
{
    my $service = shift;

    $service = defined($service) ? uc($service) : 'DNS';

    my $rows_ref = db_select(
	"select h.host,h.hostid".
	" from hosts h, hosts_groups hg, groups g".
	" where h.hostid=hg.hostid".
		" and hg.groupid=g.groupid".
		" and g.name='".PROBE_GROUP_NAME."'");

    my %result;
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

	$result{$host} = $hostid;
    }

    return \%result;
}

# get array of key nameservers ('i.ns.se,130.239.5.114', ...)
sub get_targets
{
    my $host = shift;
    my $cfg_key_out = shift;

    my $rows_ref = db_select("select key_ from items i,hosts h where i.hostid=h.hostid and h.host='$host' and i.key_ like '$cfg_key_out%'");

    my @nss;
    foreach my $row_ref (@$rows_ref)
    {
	push(@nss, get_ns_from_key($row_ref->[0]));
    }

    fail("cannot find items ($cfg_key_out*) at host ($host)") if (scalar(@nss) == 0);

    return \@nss;
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
		" and i.templateid is not null".
		" and i.key_='$key'";
    $sql .=	" and h.host like '$tld %'" if (defined($tld));

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
#   hostid1 => {itemid1 => 'i.ns.se.,2001:67c:1010:5::53', itemid2 => 'g.ns.se.,130.239.5.114', ... },
#   hostid2 => {itemid2 => 'i2.ns.se.,2001:67c:1010:5::54', itemid4 => 'g2.ns.se.,130.239.5.115', ... },
#   ...
# }
sub get_all_ns_items
{
    my $targets_ref = shift; # array reference of name servers ("name,IP")
    my $cfg_key_in = shift;
    my $tld = shift;

    my @keys;
    push(@keys, "'" . $cfg_key_in . $_ . "]'") foreach (@$targets_ref);

    my $keys_str = join(',', @keys);

    my $rows_ref = db_select(
	"select h.hostid,i.itemid,i.key_ ".
	"from items i,hosts h ".
	"where i.hostid=h.hostid".
		" and h.host like '$tld %'".
		" and i.templateid is not null".
		" and i.key_ in ($keys_str)");

    my %result;
    foreach my $row_ref (@$rows_ref)
    {
	$result{$row_ref->[0]}{$row_ref->[1]} = get_ns_from_key($row_ref->[2]);
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
    if ($complete)
    {
	$rows_ref = db_select("select itemid,hostid from items where hostid in ($hostids) and key_='$cfg_key'");
    }
    else
    {
	$rows_ref = db_select("select itemid,hostid from items where hostid in ($hostids) and key_ like '$cfg_key%'");
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

sub tld_service_enabled
{
    my $tld = shift;
    my $service_type = shift;

    $service_type = uc($service_type) if (defined($service_type));

    return SUCCESS if (not defined($service_type) or $service_type eq 'DNS');

    my $host = "Template $tld";
    my $macro = "{\$RSM.TLD.$service_type.ENABLED}";

    my $rows_ref = db_select(
	"select hm.value".
	" from hosts h,hostmacro hm".
	" where h.hostid=hm.hostid".
		" and h.host='$host'".
		" and hm.macro='$macro'");

    fail("host '$host' macro '$macro' not found") if (scalar(@$rows_ref) == 0);

    return ($rows_ref->[0]->[0] == 0 ? FAIL : SUCCESS);
}

sub handle_db_error
{
    my $msg = shift;

    fail("database error: $msg");
}

sub db_connect
{
    fail("no database configuration defined") if (not defined($config) or
						  not defined($config->{'db'}) or
						  not defined($config->{'db'}->{'name'}));

    $dbh = DBI->connect('DBI:mysql:'.$config->{'db'}->{'name'}.':'.$config->{'db'}->{'host'},
			$config->{'db'}->{'user'},
			$config->{'db'}->{'password'},
			{
			    PrintError  => 0,
			    HandleError => \&handle_db_error,
			}) or handle_db_error(DBI->errstr);

    # improve performance of selects, see
    # http://search.cpan.org/~capttofu/DBD-mysql-4.028/lib/DBD/mysql.pm
    # for details
    $dbh->{'mysql_use_result'} = 1;
}

sub db_select
{
    my $query = shift;

    dbg("[$query]");

    my $sth = $dbh->prepare($query)
	or fail("cannot prepare [$query]: ", $dbh->errstr);

    $sth->execute()
	or fail("cannot execute [$query]: ", $sth->errstr);

    my $rows_ref = $sth->fetchall_arrayref();

    my $rows = scalar(@$rows_ref);

    dbg("$rows row", ($rows != 1 ? "s" : ""));

    return $rows_ref;
}

sub set_slv_config
{
    $config = shift;

    $alerts_enabled = undef if ($config and $config->{'redis'} and $config->{'redis'}->{'enabled'} and $config->{'redis'}->{'enabled'} eq "0");
}

# Get bounds of the previous rdds test period shifted AVAIL_SHIFT_BACK seconds back.
sub get_interval_bounds
{
    my $interval = shift;

    my $t = time();
    my $till = int($t / 60) * 60 - AVAIL_SHIFT_BACK;
    my $from = $till - $interval;

    $till--;

    return ($from, $till, $till - 29);
}

# Get bounds of the previous week shifted ROLLWEEK_SHIFT_BACK seconds back.
sub get_rollweek_bounds
{
    my $t = time();
    my $till = int($t / 60) * 60 - ROLLWEEK_SHIFT_BACK;

    # mind the rollweek threshold setting
    my $rollweek_seconds = __get_macro('{$RSM.ROLLWEEK.SECONDS}');

    my $from = $till - $rollweek_seconds;

    $till--;

    return ($from, $till, $till - 29);
}

# Get bounds of previous month.
sub get_month_bounds
{
    require DateTime;

    my $dt = DateTime->now;

    $dt->truncate(to => 'month');
    my $till = $dt->epoch - 1;

    $dt->subtract(months => 1);
    my $from = $dt->epoch;

    return ($from, $till, $till - 29);
}

# Get bounds of current month.
sub get_curmon_bounds
{
    require DateTime;

    my $dt = DateTime->now;
    my $till = $dt->epoch;

    $dt->truncate(to => 'month');
    my $from = $dt->epoch;

    $dt->add(months => 1);
    $dt->subtract(seconds => 1);
    my $eomonth = $dt->epoch; # end of month

    return ($from, $till, $eomonth);
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

# Returns a reference to an array of probe names which are online from/till. The algorithm goes like this:
#
# for each manual probe status item
#   get values between $from and $till
#   if there is something
#     if there is at least one DOWN
#       add to the list
#       break
#   else
#     get the latest value before $from
#     if it is DOWN
#       add to the list
# if we did not add it to the list
#   do the same loop for automatic probe status item
#
# You must be connected to the database before calling this function.
sub get_online_probes
{
    my $from = shift;
    my $till = shift;
    my $probe_avail_limit = shift; # max "last seen" of proxy
    my $all_probes_ref = shift;

    $all_probes_ref = get_probes() unless ($all_probes_ref);
    my %reachable_probes = %$all_probes_ref; # we should work on a copy

    my (@result, @row, $sql, $host, $hostid, $rows_ref, $probe_down, $no_values);

    my $host_postfix = ' - mon';

    # Filter out unreachable probes. Probes are considered unreachable if last access time is over $probe_avail_limit seconds.
    my (@hosts, @hosts_mon);
    foreach my $host (keys(%reachable_probes))
    {
	my %h;

	$h{'host'} = $host;

	push(@hosts, \%h);
	push(@hosts_mon, "'$host$host_postfix'");

    }

    return \@result if (scalar(@hosts_mon) == 0);

    my $hosts_str = join(',', @hosts_mon);

    $rows_ref = db_select("select host,hostid from hosts where host in ($hosts_str)");

    my @hostids;
    foreach my $row_ref (@$rows_ref)
    {
	my $host = $row_ref->[0];
	my $hostid = $row_ref->[1];

	my $found = 0;
	foreach my $h (@hosts)
	{
	    if ($h->{'host'} . $host_postfix eq $host)
	    {
		    $h->{'hostid'} = $hostid;
		    $found = 1;

		    last;
	    }
	}

	fail("something impossible has just happened") unless ($found);

	push(@hostids, $hostid);
    }

    return \@result if (scalar(@hostids) == 0);

    my $hostids_str = join(',', @hostids);

    $rows_ref = db_select("select itemid,hostid from items where key_='" . PROBE_LASTACCESS_ITEM . "' and hostid in ($hostids_str)");

    my @itemids;
    foreach my $row_ref (@$rows_ref)
    {
	my $itemid = $row_ref->[0];
	my $hostid = $row_ref->[1];

	my $found = 0;
	foreach my $h (@hosts)
	{
	    if ($h->{'hostid'} == $hostid)
	    {
		    $h->{'itemid'} = $itemid;
		    $found = 1;

		    last;
	    }
	}

	fail("something impossible has just happened") unless ($found);

	push(@itemids, $itemid);
    }

    return \@result if (scalar(@itemids) == 0);

    my $itemids_str = join(',', @itemids);

    $rows_ref = db_select(
	    "select itemid".
	    " from history_uint".
	    " where itemid in ($itemids_str)".
	    	" and clock between $from and $till".
	    	" and clock-value>$probe_avail_limit");

    foreach my $row_ref (@$rows_ref)
    {
	my $itemid = $row_ref->[0];

	foreach my $h (@hosts)
	{
	    if ($h->{'itemid'} == $itemid)
	    {
		delete($reachable_probes{$h->{'host'}});
		last;
	    }
	}
    }

    foreach my $host (keys(%reachable_probes))
    {
	$hostid = $reachable_probes{$host};

	# get itemid
	my $itemid = get_itemid_by_hostid($hostid, PROBE_KEY_MANUAL);

	$rows_ref = db_select("select value from history_uint where itemid=$itemid and clock between $from and $till order by clock");

	$probe_down = 0;
	$no_values = 1;
	foreach my $row_ref (@$rows_ref)
	{
	    $no_values = 0;

	    if ($row_ref->[0] == DOWN)
	    {
		$probe_down = 1;
		dbg("$host ($hostid) down (manual: between $from and $till)");
		last;
	    }
	}

	next if ($probe_down == 1);

	if ($no_values == 1)
	{
	    # We did not get any values between $from and $till, consider the last value.

	    $rows_ref = db_select("select lastvalue from items where itemid=$itemid");

	    if (scalar(@$rows_ref) != 0)
	    {
		my $lastvalue = $rows_ref->[0]->[0];
		if (defined($lastvalue) and $lastvalue == DOWN)
		{
		    dbg("$host ($hostid) down (manual: latest)");
		    next;
		}
	    }
	}

	dbg("$host ($hostid) up (manual)");

	# Probe is considered manually up, check automatic status.

	$itemid = get_itemid_like_by_hostid($hostid, PROBE_KEY_AUTOMATIC);

	$rows_ref = db_select("select value from history_uint where itemid=$itemid and clock between $from and $till order by clock");

	$probe_down = 0;
        $no_values = 1;
	foreach my $row_ref (@$rows_ref)
        {
            $no_values = 0;

            if ($row_ref->[0] == DOWN)
            {
		dbg("$host ($hostid) down (automatic: between $from and $till)");
                $probe_down = 1;
                last;
            }
        }

	next if ($probe_down == 1);

	if ($no_values == 1)
        {
	    # We did not get any values between $from and $till, consider the latest value.

	    $rows_ref = db_select("select lastvalue from items where itemid=$itemid");

	    if (scalar(@$rows_ref) != 0)
	    {
		my $lastvalue = $rows_ref->[0]->[0];
		if (defined($lastvalue) and $lastvalue == DOWN)
		{
		    dbg("$host ($hostid) down (automatic: latest)");
		    next;
		}
	    }
	}

	push(@result, $host);
    }

    return \@result;
}

# {
#   'probe name1' => [ from1, till1, from2, till2 ... ]
#   ...
# }
sub get_probe_times
{
    my $from = shift;
    my $till = shift;
    my $probe_avail_limit = shift;
    my $probes_ref = shift; # { host => hostid, ... }

    dbg("from:$from till:$till probe_avail_limit:$probe_avail_limit");

    $probes_ref = get_probes() unless (defined($probes_ref));

    my %result;

    # check probe lastaccess time
    foreach my $probe (keys(%$probes_ref))
    {
	my $hostid = $probes_ref->{$probe};

	my $times_ref = __get_reachable_times($probe, $probe_avail_limit, $from, $till);

	if (scalar(@$times_ref) != 0)
	{
	    dbg("$probe reachable times: ", join(',', @$times_ref));

	    $times_ref = __get_probestatus_times($hostid, $times_ref, PROBE_KEY_MANUAL);
	}

	if (scalar(@$times_ref) != 0)
	{
	    dbg("$probe manual probestatus times: ", join(',', @$times_ref));

	    $times_ref = __get_probestatus_times($hostid, $times_ref, PROBE_KEY_AUTOMATIC);
	}

	if (scalar(@$times_ref) != 0)
	{
	    dbg("$probe automatic probestatus times: ", join(',', @$times_ref));

	    $result{$probe} = $times_ref;
	}
    }

    return \%result;
}

# Translate probe names to hostids of appropriate tld hosts.
#
# E. g., we have hosts (name/id):
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
    my $probes_ref = shift;

    my @result;

    my $hosts_str = "";
    foreach (@$probes_ref)
    {
	$hosts_str .= " or " unless ($hosts_str eq "");
	$hosts_str .= "host='$tld $_'";
    }

    unless ($hosts_str eq "")
    {
	my $rows_ref = db_select("select hostid from hosts where $hosts_str");

	foreach my $row_ref (@$rows_ref)
	{
	    push(@result, $row_ref->[0]);
	}
    }

    return \@result;
}

sub init_values
{
    @_sender_values = ();
}

sub push_value
{
    my $hostname = shift;
    my $key = shift;
    my $timestamp = shift;
    my $value = shift;

    my $info = join('', @_);

    push(@_sender_values, {
	'data' => {
	    'host' => $hostname,
	    'key' => $key,
	    'value' => "$value",
	    'clock' => $timestamp},
	'info' => $info,
	'tld' => $hostname});
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
    if (defined($OPTS{'debug'}))
    {
	# $tld is a global variable which is used in info()
	my $saved_tld = $tld;
	foreach my $hash_ref (@_sender_values)
        {
	    $tld = $hash_ref->{'tld'};
	    info($hash_ref->{'info'}, " (", $hash_ref->{'data'}->{'key'}, '=', $hash_ref->{'data'}->{'value'}, ")");
	}
	$tld = $saved_tld;

	return;
    }

    if (scalar(@_sender_values) == 0)
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

    my $total_values = scalar(@_sender_values);

    while (scalar(@_sender_values) > 0)
    {
	my @suba = splice(@_sender_values, 0, SENDER_BATCH_COUNT);

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
sub get_ns_from_key
{
    my $result = shift;

    $result =~ s/^[^\[]+\[([^\]]+)]$/$1/;

    my $got_params = 0;
    my $pos = length($result);

    while ($pos > 0 and $got_params < 2)
    {
        $pos--;
        my $char = substr($result, $pos, 1);
        $got_params++ if ($char eq ',')
    }

    $pos == 0 ? $got_params++ : $pos++;

    return "" unless ($got_params == 2);

    return substr($result, $pos);
}

sub is_service_error
{
    my $error = shift;

    return SUCCESS if ($error <= MAX_SERVICE_ERROR);

    return FAIL;
}

sub process_slv_ns_monthly
{
    my $tld = shift;
    my $cfg_key_in = shift;        # part of input key, e. g. 'rsm.dns.udp.upd[{$RSM.TLD},'
    my $cfg_key_out = shift;       # part of output key, e. g. 'rsm.slv.dns.ns.upd['
    my $from = shift;              # start of SLV period
    my $till = shift;              # end of SLV period
    my $value_ts = shift;          # value timestamp
    my $cfg_interval = shift;      # input values interval
    my $probe_avail_limit = shift; # max "last seen" of proxy
    my $check_value_ref = shift;   # a pointer to subroutine to check if the value was successful

    # first we need to get the list of name servers
    my $targets_ref = get_targets($tld, $cfg_key_out);

    dbg("using filter '$cfg_key_out' found next name servers:\n", Dumper($targets_ref));

    # %successful_values is a hash of name server as key and its number of successful results as a value. Name server is
    # represented by a string consisting of name and IP separated by comma. Each successful result means the IP was UP at
    # certain period. E. g.:
    #
    # 'g.ns.se.,2001:6b0:e:3::1' => 150,
    # 'b.ns.se.,192.36.133.107' => 200,
    # ...
    my %total_values;
    my %successful_values;
    foreach my $ns (@$targets_ref)
    {
	$total_values{$ns} = 0;
	$successful_values{$ns} = 0;
    }

    my $probes_ref = get_probes();

    my $all_ns_items_ref = get_all_ns_items($targets_ref, $cfg_key_in, $tld);

    dbg("using filter '$cfg_key_in' found next name server items:\n", Dumper($all_ns_items_ref));

    my $cur_from = $from;
    my ($interval, $cur_till);
    while ($cur_from < $till)
    {
	$interval = ($cur_from + $cfg_interval > $till ? $till - $cur_from : $cfg_interval);
	$cur_till = $cur_from + $interval;
	$cur_till-- unless ($cur_till == $till); # SQL BETWEEN includes upper bound

	my $online_probes_ref = get_online_probes($cur_from, $cur_till, $probe_avail_limit, $probes_ref);

	info("from:$cur_from till:$cur_till diff:", $cur_till - $cur_from, " online:", scalar(@$online_probes_ref));

	my $hostids_ref = probes2tldhostids($tld, $online_probes_ref);

	my $itemids_ref = __get_itemids_by_hostids($hostids_ref, $all_ns_items_ref);

	my $values_ref = __get_values($itemids_ref, [$cur_from, $cur_till], $all_ns_items_ref);

	foreach my $ns (keys(%$values_ref))
	{
	    my $item_values_ref = $values_ref->{$ns}->{'values'};

	    foreach (@$item_values_ref)
	    {
		$total_values{$ns}++;
		$successful_values{$ns}++ if ($check_value_ref->($_) == SUCCESS);
	    }
	}

	$cur_from += $interval;
    }

    foreach my $ns (keys(%total_values))
    {
	if ($total_values{$ns} == 0)
	{
	    info("$ns: no values found in the database for a given period");
	    next;
	}

	my $perc = sprintf("%.3f", $successful_values{$ns} * 100 / $total_values{$ns});
	my $key_out = $cfg_key_out . $ns . ']';

	push_value($tld, $key_out, $value_ts, $perc, "$ns: $perc% successful values (", $successful_values{$ns}, "/", $total_values{$ns});
    }
}

sub process_slv_monthly
{
    my $tld = shift;
    my $cfg_key_in = shift;        # e. g. 'rsm.rdds.43.rtt[{$RSM.TLD}]'
    my $cfg_key_out = shift;       # e. g. 'rsm.slv.rdds.43.rtt'
    my $from = shift;              # start of SLV period
    my $till = shift;              # end of SLV period
    my $value_ts = shift;          # value timestamp
    my $cfg_interval = shift;      # input values interval
    my $probe_avail_limit = shift; # max "last seen" of proxy
    my $check_value_ref = shift;   # a pointer to subroutine to check if the value was successful
    my $min_error = shift;         # optional: min error that relates to this item
    my $max_error = shift;         # optional: max error that relates to this item

    my $probes_ref = get_probes();

    my $all_items_ref = get_all_items($cfg_key_in);

    dbg("using filter '$cfg_key_in' found next items:\n", Dumper($all_items_ref));

    my $cur_from = $from;
    my ($interval, $cur_till);
    my $total_values = 0;
    my $successful_values = 0;

    while ($cur_from < $till)
    {
	$interval = ($cur_from + $cfg_interval > $till ? $till - $cur_from : $cfg_interval);
	$cur_till = $cur_from + $interval;
	$cur_till-- unless ($cur_till == $till); # SQL BETWEEN includes upper bound

	my $online_probes_ref = get_online_probes($cur_from, $cur_till, $probe_avail_limit, $probes_ref);

	info("from:$cur_from till:$cur_till diff:", $cur_till - $cur_from, " online:", scalar(@$online_probes_ref));

	my $hostids_ref = probes2tldhostids($tld, $online_probes_ref);

	my $itemids_ref = __get_itemids_by_hostids($hostids_ref, $all_items_ref);

	my $values_ref = __get_dbl_values($itemids_ref, $cur_from, $cur_till);

	foreach my $value (@$values_ref)
	{
	    if ($value < 0 and (defined($min_error) or defined($max_error)))
	    {
		next if ((defined($min_error) and $value < $min_error) or (defined($max_error) and $value > $max_error));
	    }

	    $total_values++;
	    $successful_values++ if ($check_value_ref->($value) == SUCCESS);
	}

	$cur_from += $interval;
    }

    if ($total_values == 0)
    {
	info("no values found in the database for a given period");
	return;
    }

    my $perc = sprintf("%.3f", $successful_values * 100 / $total_values);

    push_value($tld, $cfg_key_out, $value_ts, $perc, "$perc% successful values ($successful_values/$total_values)");
}

sub process_slv_avail
{
    my $tld = shift;
    my $cfg_key_in = shift;
    my $cfg_key_out = shift;
    my $from = shift;
    my $till = shift;
    my $value_ts = shift;
    my $cfg_minonline = shift;
    my $probe_avail_limit = shift; # max "last seen" of proxy
    my $probes_ref = shift;
    my $check_value_ref = shift;

    # calculate the availability at a particular minute
    my $probes_count = scalar(@$probes_ref);

    if ($probes_count < $cfg_minonline)
    {
	push_value($tld, $cfg_key_out, $value_ts, UP, "Up (not enough probes online, $probes_count while $cfg_minonline required)");
	add_alert(ts_str($value_ts) . "#system#zabbix#$cfg_key_out#PROBLEM#$tld (not enough probes online, $probes_count while $cfg_minonline required)") if ($alerts_enabled);
	return;
    }

    my $hostids_ref = probes2tldhostids($tld, $probes_ref);
    if (scalar(@$hostids_ref) == 0)
    {
        wrn("no probe hosts found");
        return;
    }

    my $complete_key = ("]" eq substr($cfg_key_in, -1)) ? 1 : 0;
    my $items_ref = get_items_by_hostids($hostids_ref, $cfg_key_in, $complete_key);
    if (scalar(@$items_ref) == 0)
    {
	wrn("no items ($cfg_key_in) found");
	return;
    }

    my $values_ref = get_item_values($items_ref, $from, $till);
    my $probes_with_results = scalar(keys(%$values_ref));
    if ($probes_with_results < $cfg_minonline)
    {
	push_value($tld, $cfg_key_out, $value_ts, UP, "Up (not enough probes with reults, $probes_with_results while $cfg_minonline required)");
	add_alert(ts_str($value_ts) . "#system#zabbix#$cfg_key_out#PROBLEM#$tld (not enough probes with reults, $probes_with_results while $cfg_minonline required)") if ($alerts_enabled);
	return;
    }

    my $probes_with_positive = 0;
    foreach my $itemid (keys(%$values_ref))
    {
	my $result = $check_value_ref->($values_ref->{$itemid});

	$probes_with_positive++ if (SUCCESS == $result);

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

    my $result = DOWN;
    my $perc = $probes_with_positive * 100 / $probes_with_results;
    $result = UP if ($perc > SLV_UNAVAILABILITY_LIMIT);

    push_value($tld, $cfg_key_out, $value_ts, $result, avail_result_msg($result, $probes_with_positive, $probes_with_results, $perc, $value_ts));
}

sub process_slv_ns_avail
{
    my $tld = shift;
    my $cfg_key_in = shift;
    my $cfg_key_out = shift;
    my $from = shift;
    my $till = shift;
    my $value_ts = shift;
    my $cfg_minonline = shift;
    my $probe_avail_limit = shift; # max "last seen" of proxy
    my $check_value_ref = shift;

    my $targets_ref = get_targets($tld, $cfg_key_out);

    dbg("using filter '$cfg_key_out' found next name servers:\n", Dumper($targets_ref));

    my $online_probes_ref = get_online_probes($from, $till, $probe_avail_limit, undef);

    my $online_probes_count = scalar(@$online_probes_ref);

    my $all_ns_items_ref = get_all_ns_items($targets_ref, $cfg_key_in, $tld);
    my $hostids_ref = probes2tldhostids($tld, $online_probes_ref);
    my $itemids_ref = __get_itemids_by_hostids($hostids_ref, $all_ns_items_ref);
    my $values_ref = __get_values($itemids_ref, [$from, $till], $all_ns_items_ref);

    wrn("no values of items ($cfg_key_in) at host $tld found in the database") if (scalar(keys(%$values_ref)) == 0);

    # for current month downtime
    my ($curmon_from) = get_curmon_bounds();
    my $curmon_till = $from;

    # use binds for faster execution of the same SQL query
    my $sth = get_downtime_prepare();

    foreach my $ns (keys(%$values_ref))
    {
	my $itemid = $values_ref->{$ns}->{'itemid'};
	my $item_values_ref = $values_ref->{$ns}->{'values'};

	my $out_key = $cfg_key_out . $ns . ']';

	# get current month downtime
	my $downtime = get_downtime_execute($sth, $itemid, $curmon_from, $curmon_till, 1); # ignore incidents

	push_value($tld, "rsm.slv.dns.ns.downtime[$ns]", $value_ts, $downtime,
		   "$downtime minutes of downtime from ", ts_str($curmon_from), " ($curmon_from) till ",
		   ts_str($curmon_till), " ($curmon_till)");

	# calculate other things
	my $probes_with_results = scalar(@$item_values_ref);
	my $probes_with_positive = 0;
	my $positive_sla = floor($probes_with_results * SLV_UNAVAILABILITY_LIMIT / 100);

	foreach (@$item_values_ref)
	{
	    dbg($_);
	    $probes_with_positive++ if ($check_value_ref->($_) == SUCCESS);
	}

	if ($online_probes_count < $cfg_minonline)
	{
	    push_value($tld, $out_key, $value_ts, UP, "Up (not enough probes online, $online_probes_count while $cfg_minonline required)");
	    add_alert(ts_str($value_ts) . "#system#zabbix#$out_key#PROBLEM#$tld (not enough probes online, $online_probes_count while $cfg_minonline required)") if ($alerts_enabled);
	}
	elsif ($probes_with_results < $cfg_minonline)
	{
	    push_value($tld, $out_key, $value_ts, UP, "Up (not enough probes with reults, $probes_with_results while $cfg_minonline required)");
	    add_alert(ts_str($value_ts) . "#system#zabbix#$out_key#PROBLEM#$tld (not enough probes with reults, $probes_with_results while $cfg_minonline required)") if ($alerts_enabled);
	}
	else
	{
	    my $perc = $probes_with_positive * 100 / $probes_with_results;
	    my $test_result = $perc > SLV_UNAVAILABILITY_LIMIT ? UP : DOWN;

	    push_value($tld, $out_key, $value_ts, $test_result, avail_result_msg($test_result, $probes_with_positive, $probes_with_results, $perc, $value_ts));
	}

	push_value($tld, "rsm.slv.dns.ns.results[$ns]", $value_ts, $probes_with_results, "probes with results");
	push_value($tld, "rsm.slv.dns.ns.positive[$ns]", $value_ts, $probes_with_positive, "probes with positive results");
	push_value($tld, "rsm.slv.dns.ns.sla[$ns]", $value_ts, $positive_sla, "positive results according to SLA");
    }
}

#
# get total and successful number of results of a service within given period of time for
# a specified TLD
#
sub get_results
{
    my $tld = shift;
    my $value_ts = shift;        # value timestamp
    my $probe_times_ref = shift; # probe online times (for history data)
    my $items_ref = shift;       # list of items to get results
    my $check_value_ref = shift; # a pointer to subroutine to check if the value was successful

    my %result;
    foreach my $hostid (keys(%$items_ref))
    {
	my $hostitems = $items_ref->{$hostid};
	foreach my $itemid (keys(%$hostitems))
	{
	    my $target = $items_ref->{$hostid}->{$itemid};
	    $result{$target} = {'total' => 0, 'successful' => 0} unless (exists($result{$target}));
	}
    }

    foreach my $probe (keys(%$probe_times_ref))
    {
	my $times_ref = $probe_times_ref->{$probe};
	my $hostids_ref = probes2tldhostids($tld, [$probe]);
	my $itemids_ref = __get_itemids_by_hostids($hostids_ref, $items_ref);
	my $values_ref = __get_values($itemids_ref, $times_ref, $items_ref);

	foreach my $target (keys(%$values_ref))
	{
	    my $item_values_ref = $values_ref->{$target}->{'values'};

	    foreach (@$item_values_ref)
	    {
		$result{$target}->{'total'}++;

		if ($check_value_ref->($_) == SUCCESS)
		{
		    $result{$target}->{'successful'}++;
		}
	    }

	    dbg("[$probe] $target: ", $result{$target}->{'successful'}, "/", $result{$target}->{'total'});
	}
    }

    return \%result;
}

# organize values from all hosts grouped by itemid and return itemid->values hash
#
# E. g.:
#
# '10010' => [205],
# '10011' => [-102, 304]
# ...
sub get_item_values
{
    my $items_ref = shift;
    my $from = shift;
    my $till = shift;

    my %result;

    if (0 != scalar(@$items_ref))
    {
	my $itemids_str = "";
	foreach (@$items_ref)
	{
	    $itemids_str .= "," unless ($itemids_str eq "");
	    $itemids_str .= $_->{'itemid'};
	}

	my $rows_ref = db_select("select itemid,value from history_uint where itemid in ($itemids_str) and clock between $from and $till order by clock");

	foreach my $row_ref (@$rows_ref)
	{
	    my $itemid = $row_ref->[0];
	    my $value = $row_ref->[1];

	    if (exists($result{$itemid}))
	    {
		$result{$itemid} = [@{$result{$itemid}}, $value];
	    }
	    else
	    {
		$result{$itemid} = [$value];
	    }
	}
    }

    return \%result;
}

sub check_lastclock
{
    my $lastclock = shift;
    my $value_ts = shift;
    my $interval = shift;

    return SUCCESS if (defined($OPTS{'debug'}));

    if ($lastclock + $interval > $value_ts)
    {
	dbg("lastclock:$lastclock value calculation not needed");
	return FAIL;
    }

    return SUCCESS;
}

sub get_downtime
{
    my $itemid = shift;
    my $from = shift;
    my $till = shift;
    my $ignore_incidents = shift; # if set check the whole period

    my $incidents = ($ignore_incidents) ? [$from, $till] : __get_incidents($itemid, $from, $till);

    my $count = 0;

    my $total = scalar(@$incidents);
    my $i = 0;
    my $downtime = 0;

    while ($i < $total)
    {
	my $event_from = $incidents->[$i++];
	my $event_till = $incidents->[$i++];

	fail("internal error handling incidents, check function __get_incidents()") if (($event_from < $from) and ($event_till < $from));

	my $rows_ref = db_select(
	    "select value,clock".
	    " from history_uint".
	    " where itemid=$itemid".
	    	" and clock between $event_from and $event_till".
	    " order by clock");

	my $prevvalue = UP;
	my $prevclock = 0;

	foreach my $row_ref (@$rows_ref)
	{
	    my $value = $row_ref->[0];
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
	$downtime += $event_till - $prevclock if ($prevvalue == DOWN);
    }

    return $downtime / 60; # minutes
}

sub get_downtime_prepare
{
    my $query =
	"select value,clock".
	" from history_uint".
	" where itemid=?".
	    " and clock between ? and ?".
	" order by clock";

    my $sth = $dbh->prepare($query)
        or fail("cannot prepare [$query]: ", $dbh->errstr);

    dbg("[$query]");

    return $sth;
}

sub get_downtime_execute
{
    my $sth = shift;
    my $itemid = shift;
    my $from = shift;
    my $till = shift;
    my $ignore_incidents = shift; # if set check the whole period

    my $incidents = ($ignore_incidents) ? [$from, $till] : __get_incidents($itemid, $from, $till);

    my $count = 0;

    my $total = scalar(@$incidents);
    my $i = 0;
    my $downtime = 0;

    while ($i < $total)
    {
	my $event_from = $incidents->[$i++];
	my $event_till = $incidents->[$i++];

	fail("internal error handling incidents, check function __get_incidents()") if (($event_from < $from) and ($event_till < $from));

	$sth->bind_param(1, $itemid);
	$sth->bind_param(2, $event_from);
	$sth->bind_param(3, $event_till);

	$sth->execute()
	    or fail("cannot execute query: ", $sth->errstr);

	my ($value, $clock);
	$sth->bind_columns(\$value, \$clock);

	my $prevvalue = UP;
	my $prevclock = 0;

	while ($sth->fetch)
	{
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
	$downtime += $event_till - $prevclock if ($prevclock != 0 and $prevvalue == DOWN);

	$sth->finish();
    }

    return $downtime / 60; # minutes
}

sub avail_result_msg
{
    my $test_result = shift;
    my $success_values = shift;
    my $total_results = shift;
    my $perc = shift;
    my $value_ts = shift;

    my $result_str = ($test_result == UP ? "Up" : "Down");

    return sprintf("$result_str (%d/%d positive, %.3f%%, %s)", $success_values, $total_results, $perc, ts_str($value_ts));
}

sub slv_exit
{
    my $rv = shift;

    if (defined($pidfile))
    {
	$pidfile->remove() or wrn("cannot remove pid file ", $pidfile->file());
    }

    exit($rv);
}

sub exit_if_running
{
    return if (defined($OPTS{'debug'}));

    my $filename = __get_pidfile();

    $pidfile = File::Pid->new({ file => $filename });
    fail("cannot lock script") unless (defined($pidfile));

    $pidfile->write() or fail("cannot write to a pid file ", $pidfile->file);

    return if ($pidfile->pid == $$);

    # pid file exists and has valid pid
    if (my $pid = $pidfile->running())
    {
	wrn("already running (pid:$pid)");
	exit(SUCCESS);
    }

    # pid file exists but the pid in it is invalid
    $pidfile->remove() or fail("cannot remove pid file ", $pidfile->file);

    $pidfile = File::Pid->new({ file => $filename });
    fail("cannot lock script") unless (defined($pidfile));

    $pidfile->write() or fail("cannot write to a pid file ", $pidfile->file);
}

sub dbg
{
    return unless (defined($OPTS{'debug'}));

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

    exit(FAIL);
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
    GetOptions(\%OPTS, "help!", "debug!", @_) or pod2usage(2);
    pod2usage(1) if ($OPTS{'help'});
}

sub ts_str
{
    my $ts = shift;
    $ts = time() unless ($ts);

    my ($sec, $min, $hour, $mday, $mon, $year, $wday, $yday, $isdst) = localtime($ts);

    $year += 1900;
    $mon++;

    return sprintf("%4.2d/%2.2d/%2.2d %2.2d:%2.2d:%2.2d", $year, $mon, $mday, $hour, $min, $sec);
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

sub __log
{
	my $syslog_priority = shift;
	my $msg = shift;

	my $priority;

	if ($syslog_priority eq 'info')
	{
		$priority = 'INF';
	}
	elsif ($syslog_priority eq 'err')
	{
		$priority = 'ERR';
	}
	elsif ($syslog_priority eq 'warning')
	{
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

	my $cur_tld = $tld || "";

	if (defined($OPTS{'debug'}))
	{
		print(ts_str(), " [$priority]", ($cur_tld eq "" ? "" : " $cur_tld:"), " $msg\n");
		return;
	}

	my $ident = ($cur_tld eq "" ? "" : "$cur_tld-") . $program;

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

	syslog($syslog_priority, ts_str() . " [$priority] $msg"); # first paramtere is not used in our rsyslog template

	$prev_tld = $cur_tld;
}

sub __get_macro
{
    my $m = shift;

    my $rows_ref = db_select("select value from globalmacro where macro='$m'");

    fail("cannot find macro '$m'") unless (1 == scalar(@$rows_ref));

    return $rows_ref->[0]->[0];
}

# organize values from all hosts grouped by target and return "target"->values hash
#
# E. g.:
#
# 'g.ns.se.,2001:6b0:e:3::1' => [205],
# 'b.ns.se.,192.36.133.107' => [-202, 304],
# ...
#
# or
#
# '' => [503, -203, ...],
sub __get_values
{
    my $itemids_ref = shift;
    my $times_ref = shift; # from, till, ...
    my $items_ref = shift;

    my %result;

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

	    my $rows_ref = db_select("select itemid,value from history where itemid in ($itemids_str) and clock between $from and $till order by clock");

	    foreach my $row_ref (@$rows_ref)
	    {
		my $itemid = $row_ref->[0];
		my $value = $row_ref->[1];

		my $target;
		my $last = 0;
		foreach my $hostid (keys(%$items_ref))
		{
		    foreach my $i (keys(%{$items_ref->{$hostid}}))
		    {
			if ($i == $itemid)
			{
			    $target = $items_ref->{$hostid}{$i};
			    $last = 1;
			    last;
			}
		    }
		    last if ($last == 1);
		}

		fail("internal error: name server of item $itemid not found") unless (defined($target));

		if (exists($result{$target}))
		{
		    push(@{$result{$target}->{'values'}}, $value);
		}
		else
		{
		    my %h;

		    $h{'itemid'} = $itemid;
		    $h{'values'} = [$value];

		    $result{$target} = \%h;
		}
	    }
	}
    }

    return \%result;
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

#
# returns: [itemid1, itemid2 ...]
#
sub __get_itemids_by_hostids
{
    my $hostids_ref = shift;
    my $all_items = shift;

    my @result = ();

    foreach my $hostid (@$hostids_ref)
    {
	unless ($all_items->{$hostid})
	{
	    dbg("\nhostid $hostid from:\n", Dumper($hostids_ref), "was not found in:\n", Dumper($all_items));
	    fail("internal error: no hostid $hostid in input items");
	}

	foreach my $itemid (keys(%{$all_items->{$hostid}}))
	{
	    push(@result, $itemid);
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

sub __func
{
    my $func = (caller(4))[3];

    if (defined($func))
    {
	$func =~ s/^[^:]*::(.*)$/$1/;
	$func .= "()";
    }

    return $func;
}

sub __get_pidfile
{
    return PID_DIR . '/' . __script() . '.pid';
}

# return incidents (start and end times) in array:
#
# [
#   1386066210, 1386340110,
#   1386340290, 1386340470
# ]
#
# An incident is a period when the problem was active. This period is
# limited by 2 events, the PROBLEM event and the first OK event after
# that.
#
# first ongoing incident will have start time specified as $from
# last ongoing incident will have end time specified as $till
sub __get_incidents
{
    my $itemid = shift;
    my $from = shift;
    my $till = shift;

    my (@incidents, $rows_ref, $row_ref);

    $rows_ref = db_select(
	"select distinct t.triggerid".
	" from triggers t,functions f".
	" where t.triggerid=f.triggerid".
		" and f.itemid=$itemid".
		" and t.priority=".TRIGGER_SEVERITY_NOT_CLASSIFIED);

    my $rows = scalar(@$rows_ref);

    unless ($rows == 1)
    {
	wrn("item $itemid must have one not classified trigger (found: $rows)");
	return \@incidents;
    }

    my $triggerid = $rows_ref->[0]->[0];

    # select previous event to see if $from time gets into PROBLEM state of a trigger
    $rows_ref = db_select(
	"select max(clock)".
	" from events".
	" where object=".EVENT_OBJECT_TRIGGER.
		" and source=".EVENT_SOURCE_TRIGGERS.
		" and objectid=$triggerid".
		" and clock<$from");

    my $last_trigger_value = TRIGGER_VALUE_FALSE;

    $row_ref = $rows_ref->[0];

    if (defined($row_ref) and defined($row_ref->[0]))
    {
	my $preincident_clock = $row_ref->[0];

	$rows_ref = db_select(
	    "select value,false_positive".
	    " from events".
	    " where object=".EVENT_OBJECT_TRIGGER.
	    	" and source=".EVENT_SOURCE_TRIGGERS.
	    	" and objectid=$triggerid".
	    	" and clock=$preincident_clock".
	    " order by ns desc".
	    " limit 1");

	$row_ref = $rows_ref->[0];

	my $value = $row_ref->[0];
	my $false_positive = $row_ref->[1];

	dbg("event before rolling week detected: value:$value false_positive:$false_positive");

	# we cannot add 'value=TRIGGER_VALUE_TRUE' and 'false_positive!=INCIDENT_FALSE_POSITIVE'
	# to the SQL query above as we need the latest event before $from
	if ($value == TRIGGER_VALUE_TRUE and $false_positive != INCIDENT_FALSE_POSITIVE)
	{
	    push(@incidents, $from);

	    $last_trigger_value = $value;
	}
    }

    $rows_ref = db_select(
	"select clock,value,false_positive".
	" from events".
	" where object=".EVENT_OBJECT_TRIGGER.
		" and source=".EVENT_SOURCE_TRIGGERS.
		" and objectid=$triggerid".
		" and clock between $from and $till".
	" order by clock,ns");

    my @unsorted_incidents;

    foreach my $row_ref (@$rows_ref)
    {
	my $clock = $row_ref->[0];
	my $value = $row_ref->[1];
	my $false_positive = $row_ref->[2];

	dbg("event: clock:$clock value:$value false_positive:$false_positive");

	# incident is stopped unless PROBLEM and NOT FALSE POSITIVE event
	if ($value != TRIGGER_VALUE_FALSE)
	{
	    $value = TRIGGER_VALUE_FALSE unless ($value == TRIGGER_VALUE_TRUE and $false_positive != INCIDENT_FALSE_POSITIVE);
	}

	next if ($value == $last_trigger_value);

	push(@incidents, $clock);

	$last_trigger_value = $value;
    }

    push(@incidents, $_) foreach (sort(@unsorted_incidents));
    push(@incidents, $till) if ($last_trigger_value == TRIGGER_VALUE_TRUE);

    # debug
    my $i = 0;
    while ($i < scalar(@incidents))
    {
	my $inc_from = $incidents[$i++];
	my $inc_till = $incidents[$i++];
	dbg(ts_str($inc_from), " ($inc_from) -> ", ts_str($inc_till), " ($inc_till)");
    }

    return \@incidents;
}

# Times when probe "lastaccess" within $probe_avail_limit.
sub __get_reachable_times
{
    my $probe = shift;
    my $probe_avail_limit = shift;
    my $from = shift;
    my $till = shift;

    my $host = "$probe - mon";

    my ($rows_ref, @times, $last_status);

    $rows_ref = db_select(
	"select hi.clock,hi.value".
	" from items i,history_uint hi,hosts h".
	" where i.itemid=hi.itemid".
		" and i.hostid=h.hostid".
	    	" and i.key_='".PROBE_LASTACCESS_ITEM."'".
	    	" and hi.clock between ".($from-3600)." and ".($from-1).
	    	" and h.host='$host'".
	" order by hi.clock desc".
	" limit 1");

    $last_status = UP;
    if (scalar(@$rows_ref) != 0)
    {
	my $clock = $rows_ref->[0]->[0];
	my $value = $rows_ref->[0]->[1];

	dbg("clock:$clock value:$value");

	$last_status = DOWN if ($clock - $value > $probe_avail_limit);
    }

    push(@times, $from) if ($last_status == UP);

    $rows_ref = db_select(
	"select hi.clock,hi.value".
	" from items i,history_uint hi,hosts h".
	" where i.itemid=hi.itemid".
		" and i.hostid=h.hostid".
	    	" and i.key_='".PROBE_LASTACCESS_ITEM."'".
	    	" and hi.clock between $from and $till".
	    	" and h.host='$host'".
	    	" and hi.value!=0".
	" order by hi.clock");

    foreach my $row_ref (@$rows_ref)
    {
	my $clock = $row_ref->[0];
	my $value = $row_ref->[1];

	my $status = ($clock - $value > $probe_avail_limit) ? DOWN : UP;

	if ($last_status != $status)
	{
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

    $rows_ref = db_select("select value from history_uint where itemid=$itemid and clock<" . $times_ref->[0] . " order by clock desc limit 1");

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

	$rows_ref = db_select("select clock,value from history_uint where itemid=$itemid and clock between $from and $till order by clock");

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

    my $itemid = get_itemid_by_key("$item_prefix.configvalue[$item_param]");

    while (not $value and $diff < $month)
    {
	my $rows_ref = db_select("select value from history_uint where itemid=$itemid and clock between " . ($value_time - $diff) . " and $value_time order by clock desc limit 1");

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
