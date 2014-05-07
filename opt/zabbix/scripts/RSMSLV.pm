package RSMSLV;

use strict;
use warnings;
use DBI;
use Getopt::Long;
use Exporter qw(import);
use Zabbix;
use Sender;
use File::Pid;

use constant SUCCESS => 0;
use constant FAIL => 1;
use constant UP => 1;
use constant DOWN => 0;
use constant SLV_UNAVAILABILITY_LIMIT => 49; # NB! must be in sync with frontend

use constant MAX_SERVICE_ERROR => -200; # -200, -201 ...
use constant RDDS_UP => 2; # results of input items: 0 - RDDS down, 1 - only RDDS43 up, 2 - both RDDS43 and RDDS80 up
use constant MIN_LOGIN_ERROR => -205;
use constant MAX_LOGIN_ERROR => -203;
use constant MIN_UPDATE_ERROR => -208;
use constant MAX_UPDATE_ERROR => -206;
use constant MIN_INFO_ERROR => -211;
use constant MAX_INFO_ERROR => -209;

use constant TRIGGER_SEVERITY_NOT_CLASSIFIED => 0;
use constant TRIGGER_VALUE_CHANGED_YES => 1;
use constant EVENT_OBJECT_TRIGGER => 0;
use constant EVENT_SOURCE_TRIGGERS => 0;
use constant TRIGGER_VALUE_TRUE => 1;
use constant INCIDENT_FALSE_POSITIVE => 1; # NB! must be in sync with frontend

use constant SECONDS_WEEK => 604800;

use constant SENDER_BATCH_COUNT => 250;

# In order to do the calculation we should wait till all the results
# are available on the server (from proxies). We shift back 2 minutes
# in case of "availability" and 3 minutes in case of "rolling week"
# calculations.
# NB! These numbers must be in sync with Frontend (details page)!
use constant AVAIL_SHIFT_BACK => 120; # seconds (must be divisible by 60 without remainder)
use constant ROLLWEEK_SHIFT_BACK => 180; # seconds (must be divisible by 60 without remainder)

our ($result, $dbh, $tld);

our %OPTS; # command-line options

our @EXPORT = qw($result $dbh $tld %OPTS
		SUCCESS FAIL UP DOWN RDDS_UP SLV_UNAVAILABILITY_LIMIT
		get_macro_minns get_macro_dns_probe_online get_macro_rdds_probe_online get_macro_dns_rollweek_sla
		get_macro_rdds_rollweek_sla get_macro_dns_udp_rtt_high get_macro_dns_udp_rtt_low
		get_macro_dns_tcp_rtt_low get_macro_rdds_rtt_low get_macro_dns_udp_delay get_macro_dns_tcp_delay
		get_macro_rdds_delay get_macro_epp_delay get_macro_epp_probe_online get_macro_epp_rollweek_sla
		get_macro_dns_update_time get_macro_rdds_update_time get_items_by_hostids get_tld_items
		get_macro_epp_rtt_low get_macro_probe_avail_limit get_item_data get_lastclock get_tlds
		db_connect db_select
		set_slv_config get_interval_bounds get_rollweek_bounds get_month_bounds
		minutes_last_month get_online_probes probes2tldhostids init_values push_value send_values
		get_ns_from_key is_service_error process_slv_ns_monthly process_slv_ns_avail process_slv_monthly
		get_item_values check_lastclock get_down_count avail_result_msg
		dbg info wrn fail slv_exit exit_if_running trim parse_opts ts_str);

my $probe_group_name = 'Probes';
my $probe_key_manual = 'probe.status[manual]';
my $probe_key_automatic = 'probe.status[automatic,%]'; # match all in SQL

# configuration, set in set_slv_config()
my $config = undef;

# make sure only one copy of script runs (unless in test mode)
my $pidfile;
my $pid_dir = '/tmp';

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

    my $res;

    if ("[" eq substr($cfg_key_out, -1))
    {
	$res = db_select(
	    "select i.key_,i.itemid,i.lastclock".
	    " from items i,hosts h".
	    " where i.hostid=h.hostid".
	    	" and h.host='$host'".
	    	" and (i.key_='$cfg_key_in' or i.key_ like '$cfg_key_out%')");
    }
    else
    {
	$res = db_select(
	    "select i.key_,i.itemid,i.lastclock".
	    " from items i,hosts h".
	    " where i.hostid=h.hostid".
	    	" and h.host='$host'".
	    	" and i.key_ in ('$cfg_key_in','$cfg_key_out')");
    }

    my $arr_ref = $res->fetchall_arrayref();

    my $rows = scalar(@$arr_ref);

    fail("cannot find items ($cfg_key_in and $cfg_key_out) at host ($host)") if ($rows < 2);

    my $itemid_in = undef;
    my $itemid_out = undef;
    my $lastclock = undef;

    foreach (@$arr_ref)
    {
	my $row = $_;

	if ($row->[0] eq $cfg_key_in)
	{
	    $itemid_in = $row->[1];
	}
	else
	{
	    $itemid_out = $row->[1];
	    $lastclock = $row->[2] ? $row->[2] : 0;
	}

	last if (defined($itemid_in) and defined($itemid_out));
    }

    fail("cannot find itemid ($cfg_key_in and $cfg_key_out) at host ($host)")
	unless (defined($itemid_in) and defined($itemid_out));

    return ($itemid_in, $itemid_out, $lastclock);
}

sub get_lastclock
{
    my $host = shift;
    my $key = shift;

    my $res;

    if ("[" eq substr($key, -1))
    {
	$res = db_select(
	    "select i.lastclock".
	    " from items i,hosts h".
	    " where i.hostid=h.hostid".
	    	" and h.host='$host'".
	    	" and i.key_ like '$key%'");
    }
    else
    {
	$res = db_select(
	    "select i.lastclock".
	    " from items i,hosts h".
	    " where i.hostid=h.hostid".
	    	" and h.host='$host'".
	    	" and i.key_='$key'");
    }

    my $arr_ref = $res->fetchall_arrayref();

    fail("lastclock check failed: cannot find item ($key) at host ($host)") if (scalar(@$arr_ref) < 1);

    return $arr_ref->[0]->[0] ? $arr_ref->[0]->[0] : 0;
}

sub get_tlds
{
    my $service = shift;

    my $res;

    unless (defined($service))
    {
	$res = db_select(
	    "select h.host".
	    " from hosts h,hosts_groups hg,groups g".
	    " where h.hostid=hg.hostid".
		" and hg.groupid=g.groupid".
		" and g.name='TLDs'".
		" and h.status=0");
    }
    else
    {
	$res = db_select(
	    "select h.host".
	    " from hosts h,hosts_groups hg,groups g,hosts h2,hostmacro hm".
	    " where h.hostid=hg.hostid".
	    	" and hg.groupid=g.groupid".
	    	" and h2.name=concat('Template ', h.host)".
	    	" and g.name='TLDs'".
	    	" and h2.hostid=hm.hostid".
	    	" and hm.macro='{\$RSM.TLD.".uc($service).".ENABLED}'".
	    	" and hm.value!=0".
	    	" and h.status=0");
    }

    my @tlds;
    while (my @row = $res->fetchrow_array)
    {
	push(@tlds, $row[0]);
    }

    return \@tlds;
}

sub get_items_by_hostids
{
    my $hostids_ref = shift;
    my $cfg_key = shift;
    my $complete = shift;

    my $hostids = join(',', @$hostids_ref);
    dbg("hostids: $hostids");

    my $res;
    if ($complete)
    {
	$res = db_select("select itemid,hostid from items where hostid in ($hostids) and key_='$cfg_key'");
    }
    else
    {
	$res = db_select("select itemid,hostid from items where hostid in ($hostids) and key_ like '$cfg_key%'");
    }

    my @items;
    while (my @row = $res->fetchrow_array)
    {
	my %hash;
	$hash{'itemid'} = $row[0];
	$hash{'hostid'} = $row[1];
	push(@items, \%hash);
    }

    fail("cannot find items ($cfg_key", ($complete ? '' : '*'), ") at hostids ($hostids)") if (scalar(@items) == 0);

    return \@items;
}

sub get_tld_items
{
    my $tld = shift;
    my $cfg_key = shift;

    my $res = db_select(
	"select i.itemid,i.key_".
	" from items i,hosts h".
	" where i.hostid=h.hostid".
		" and h.host='$tld'".
		" and i.key_ like '$cfg_key%'");

    my @items;
    while (my @row = $res->fetchrow_array)
    {
	push(@items, \@row);
    }

    fail("cannot find items ($cfg_key*) at host ($tld)") if (scalar(@items) == 0);

    return \@items;
}

sub handle_db_error
{
    my $msg = shift;

    fail("cannot connect to database: $msg");
}

sub db_connect
{
    $dbh = DBI->connect('DBI:mysql:'.$config->{'db'}->{'name'}.':'.$config->{'db'}->{'host'},
			$config->{'db'}->{'user'},
			$config->{'db'}->{'password'},
			{
			    PrintError  => 0,
			    HandleError => \&handle_db_error,
			}) or handle_db_error(DBI->errstr);
}

sub db_select
{
    my $query = shift;

    dbg($query);

    my $res = $dbh->prepare($query)
	or fail("cannot prepare [$query]: ", $dbh->errstr);

    my $rv = $res->execute()
	or fail("cannot execute [$query]: ", $res->errstr);

    return $res;
}

sub set_slv_config
{
    $config = shift;
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
    my $from = $till - SECONDS_WEEK;

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
    my $probe_avail_limit = shift;
    my $all_probes_ref = shift;

    $all_probes_ref = __get_probes() unless ($all_probes_ref);

    my (@result, @row, $sql, $host, $hostid, $res, $probe_down, $no_values);

    # Filter out unreachable probes. Probes are considered unreachable if last access time is over 60 seconds.
    my $probe_availability_item = 'zabbix[proxy,{$RSM.PROXY_NAME},lastaccess]';

    my $hosts_mon = '';
    foreach my $host (keys(%$all_probes_ref))
    {
	$hosts_mon .= ',' if ($hosts_mon ne '');
	$hosts_mon .= "'$host - mon'";
    }

    return \@result if ($hosts_mon eq '');

    $res = db_select(
	"select distinct h.host".
	" from items i,history_uint hi,hosts h".
	" where i.itemid=hi.itemid".
	    " and i.hostid=h.hostid".
	    " and i.key_='$probe_availability_item'".
	    " and hi.clock between $from and $till".
	    " and hi.clock-hi.value > $probe_avail_limit".
	    " and h.host in ($hosts_mon)");

    while (@row = $res->fetchrow_array)
    {
	my $h = $row[0];
	$h =~ s/^(\S+).*/$1/; # remove ' - mon' from the host name
	delete($all_probes_ref->{$h});
    }

    foreach my $host (keys(%$all_probes_ref))
    {
	$hostid = $all_probes_ref->{$host};

	$res = db_select(
	    "select h.value".
	    " from history_uint h,items i".
	    " where h.itemid=i.itemid".
	    	" and i.key_='$probe_key_manual'".
	    	" and i.hostid=$hostid".
	    	" and h.clock between $from and $till");

	$probe_down = 0;
	$no_values = 1;
	while (@row = $res->fetchrow_array)
	{
	    $no_values = 0;

	    if ($row[0] == DOWN)
	    {
		$probe_down = 1;
		dbg("  $host ($hostid) down (manual: between $from and $till)");
		last;
	    }
	}

	next if ($probe_down == 1);

	if ($no_values == 1)
	{
	    # We did not get any values between $from and $till, consider the last value.

	    $res = db_select("select lastvalue from items where key_='$probe_key_manual' and hostid=$hostid");

	    if (@row = $res->fetchrow_array)
	    {
		if (defined($row[0]) and $row[0] == DOWN)
		{
		    dbg("  $host ($hostid) down (manual: latest)");
		    next;
		}
	    }
	}

	dbg("  $host ($hostid) up (manual)");

	# Probe is considered manually up, check automatic status.

	$res = db_select(
	    "select h.value".
	    " from history_uint h,items i".
	    " where h.itemid=i.itemid".
	    	" and i.key_ like '$probe_key_automatic'".
	    	" and i.hostid=$hostid".
	    	" and h.clock between $from and $till");

	$probe_down = 0;
        $no_values = 1;
	while (@row = $res->fetchrow_array)
        {
            $no_values = 0;

            if ($row[0] == DOWN)
            {
		dbg("  $host ($hostid) down (automatic: between $from and $till)");
                $probe_down = 1;
                last;
            }
        }

	next if ($probe_down == 1);

	if ($no_values == 1)
        {
	    # We did not get any values between $from and $till, consider the latest value.

	    $res = db_select("select lastvalue from items where key_='$probe_key_automatic' and hostid=$hostid");

	    if (@row = $res->fetchrow_array)
	    {
		if (defined($row[0]) and $row[0] == DOWN)
		{
		    dbg("  $host ($hostid) down (automatic: latest)");
		    next;
		}
	    }
	}

	push(@result, $host);
    }

    return \@result;
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
	my $res = db_select("select hostid from hosts where $hosts_str");

	my @row;
	push(@result, $row[0]) while (@row = $res->fetchrow_array);
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

    push(@_sender_values, {'host' => $hostname, 'key' => $key,  'value' => "$value", 'clock' => $timestamp});
}

#
# accepts array reference of values:
#
# [
#   {'host' => 'host1', 'key' => 'item1', 'value' => '5', 'clock' => 1391790685},
#   {'host' => 'host2', 'key' => 'item1', 'value' => '4', 'clock' => 1391790685},
#   ...
# ]
#
sub send_values
{
    return if (defined($OPTS{'test'}));

    if (scalar(@_sender_values) == 0)
    {
	wrn("will not send values, nothing to send");
	return;
    }

    my $sender = Zabbix::Sender->new({
	'server' => $config->{'slv'}->{'zserver'},
	'port' => $config->{'slv'}->{'zport'},
	'retries' => 1 });

    fail("cannot connect to Zabbix server") unless (defined($sender));

    my @suba;
    my $total_values = scalar(@_sender_values);

    while (scalar(@_sender_values) > 0)
    {
	@suba = splice(@_sender_values, 0, SENDER_BATCH_COUNT);

	dbg("sending ", scalar(@suba), "/$total_values values");

	unless (defined($sender->send_arrref(\@suba)))
	{
	    wrn("cannot send data to Zabbix server:");

	    foreach my $entry (@suba)
	    {
		my $line = '{';

		$line .= ($line ne '{' ? ', ' : '') . $_ . ' => ' . $entry->{$_} foreach (keys(%$entry));

		$line .= '}';

		wrn($line);
	    }
	}
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
    my $probe_avail_limit = shift; # macro value
    my $check_value_ref = shift;   # a pointer to subroutine to check if the value was successful

    # first we need to get the list of name servers
    my $nss_ref = __get_nss($tld, $cfg_key_out);

    # %successful_values is a hash of name server as key and its number of successful results as a value. Name server is
    # represented by a string consisting of name and IP separated by comma. Each successful result means the IP was UP at
    # certain period. E. g.:
    #
    # 'g.ns.se.,2001:6b0:e:3::1' => 150,
    # 'b.ns.se.,192.36.133.107' => 200,
    # ...
    my %total_values;;
    my %successful_values;
    foreach my $ns (@$nss_ref)
    {
	$total_values{$ns} = 0;
	$successful_values{$ns} = 0;
    }

    my $probes_ref = __get_probes();

    my $all_ns_items_ref = __get_all_ns_items($nss_ref, $cfg_key_in, $tld);

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

	my $items_ref = __get_online_items($hostids_ref, $all_ns_items_ref);

	my $values_ref = __get_ns_values($items_ref, $cur_from, $cur_till, $all_ns_items_ref);

	foreach my $ns (keys(%$values_ref))
	{
	    my $item_values_ref = $values_ref->{$ns};

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

	my $key_out = $cfg_key_out . $ns . ']';
	my $perc = sprintf("%.3f", $successful_values{$ns} * 100 / $total_values{$ns});

	info("$ns: $perc% successful values (", $successful_values{$ns}, " out of ", $total_values{$ns});
	push_value($tld, $key_out, $value_ts, $perc);
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
    my $probe_avail_limit = shift; # macro value
    my $check_value_ref = shift;   # a pointer to subroutine to check if the value was successful
    my $min_error = shift;         # optional: min error that relates to this item
    my $max_error = shift;         # optional: max error that relates to this item

    my $probes_ref = __get_probes();

    my $all_items_ref = __get_all_items($cfg_key_in);

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

	my $online_items_ref = __get_online_items($hostids_ref, $all_items_ref);

	my $values_ref = __get_dbl_values($online_items_ref, $cur_from, $cur_till);

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

    info("$perc% successful values ($successful_values out of $total_values)");
    push_value($tld, $cfg_key_out, $value_ts, $perc);
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
    my $unavail_limit = shift;
    my $probe_avail_limit = shift;
    my $check_value_ref = shift;

    my $nss_ref = __get_nss($tld, $cfg_key_out);

    my @out_keys;
    push(@out_keys, $cfg_key_out . $_ . ']') foreach (@$nss_ref);

    my $online_probes_ref = get_online_probes($from, $till, $probe_avail_limit, undef);
    my $count = scalar(@$online_probes_ref);
    if ($count < $cfg_minonline)
    {
	info("Up (not enough probes online, $count while $cfg_minonline required)");
	push_value($tld, $_, $value_ts, UP) foreach (@out_keys);
	return;
    }

    my $all_ns_items_ref = __get_all_ns_items($nss_ref, $cfg_key_in, $tld);

    my $hostids_ref = probes2tldhostids($tld, $online_probes_ref);

    my $online_items_ref = __get_online_items($hostids_ref, $all_ns_items_ref);

    my $values_ref = __get_ns_values($online_items_ref, $from, $till, $all_ns_items_ref);

    wrn("no values of items ($cfg_key_in) at host $tld found in the database") if (scalar(keys(%$values_ref)) == 0);

    foreach my $ns (keys(%$values_ref))
    {
	my $item_values_ref = $values_ref->{$ns};
	my $total_values = scalar(@$item_values_ref);
	my $out_key = $cfg_key_out . $ns . ']';

	if ($total_values < $cfg_minonline)
	{
	    info("$ns Up (not enough probes with reults, $total_values while $cfg_minonline required)");
	    push_value($tld, $out_key, $value_ts, UP);
	    next;
	}

	my $success_values = 0;
	foreach (@$item_values_ref)
	{
	    info("  ", $_);
	    $success_values++ if ($check_value_ref->($_) == SUCCESS);
	}

	my $perc = $success_values * 100 / $total_values;
	my $test_result = $perc > $unavail_limit ? UP : DOWN;

	info($ns, ": ", avail_result_msg($test_result, $success_values, $total_values, $perc, $value_ts));
	push_value($tld, $out_key, $value_ts, $test_result);
    }
}

# organize values from all hosts grouped by itemid and return itemid->values hash
#
# E. g.:
#
# '10010' => [
#          205
#    ];
# '10011' => [
#          -102
#          304
#    ];
sub get_item_values
{
    my $items_ref = shift;
    my $from = shift;
    my $till = shift;

    my %result;

    if (0 < scalar(@$items_ref))
    {
	my $items_str = "";
	foreach (@$items_ref)
	{
	    $items_str .= "," unless ($items_str eq "");
	    $items_str .= $_->{'itemid'};
	}

	my $res = db_select("select itemid,value from history_uint where itemid in ($items_str) and clock between $from and $till");

	while (my @row = $res->fetchrow_array)
	{
	    my $itemid = $row[0];
	    my $value = $row[1];

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

    return SUCCESS if (defined($OPTS{'test'}));

    if ($lastclock + $interval > $value_ts)
    {
	dbg("lastclock:$lastclock value calculation not needed");
	return FAIL;
    }

    return SUCCESS;
}

sub get_down_count
{
    my $itemid = shift;
    my $from = shift;
    my $till = shift;

    my $eventtimes = __get_eventtimes($itemid, $from, $till);

    my $count = 0;

    my $total = scalar(@$eventtimes);
    my $i = 0;
    while ($i < $total)
    {
	my $event_from = $eventtimes->[$i++];
	my $event_till = $eventtimes->[$i++];

	my $res = db_select("select count(*) from history_uint where itemid=$itemid and clock between $event_from and $event_till and value=" . DOWN);

	my $n = ($res->fetchrow_array)[0];

	info("  $n down between $event_from and $event_till");

	$count += $n;
    }

    return $count;
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
    return if (defined($OPTS{'test'}));

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

    __log(join('', '[', ts_str(), '] [', __script(), (defined($tld) ? " $tld" : ''), '] [DBG] ', @_, "\n"));
}

sub info
{
    __log(join('', '[', ts_str(), '] [', __script(), (defined($tld) ? " $tld" : ''), '] [INF] ', @_, "\n"));
}

sub wrn
{
    __log(join('', '[', ts_str(), '] [', __script(), (defined($tld) ? " $tld" : ''), '] [WRN] ', @_, "\n"));
}

sub fail
{
    my $msg = join('', '[', ts_str(), '] [', __script(), (defined($tld) ? " $tld" : ''), '] [ERR] ', @_, "\n");

    # log failures to the general log (not TLD-specific)
    $tld = undef;

    __log($msg);

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
    usage() unless (GetOptions(\%OPTS, "debug!", "stdout!", "test!"));
    $tld = $ARGV[0] if (defined($ARGV[0]));
}

sub ts_str
{
    my $ts = shift;
    $ts = time() unless ($ts);

    my ($sec, $min, $hour, $mday, $mon, $year, $wday, $yday, $isdst) = localtime($ts);

    $year += 1900;
    $mon++;
    return sprintf("%4.2d/%2.2d/%2.2d-%2.2d:%2.2d:%2.2d", $year, $mon, $mday, $hour, $min, $sec);
}

sub usage
{
    print("usage: $0 <tld> [options]\n");
    print("Options:\n");
    print("    --debug    print more details\n");
    print("    --stdout   print output to stdout instead of log file\n");
    print("    --test     run the script in test mode, this means:\n");
    print("               - skip checks if need to recalculate value\n");
    print("               - do not send the value to the server\n");
    print("               - print the output to stdout instead of logging it (implies --stdout)\n");
    exit(FAIL);
}

#################
# Internal subs #
#################

sub __log
{
    my $msg = shift;

    if (defined($OPTS{'test'}) or
	defined($OPTS{'stdout'}) or
	not defined($config) or
	not defined($config->{'slv'}) or
	not defined($config->{'slv'}->{'logdir'}))
    {
	print($msg);
	return;
    }

    my $OUTFILE;

    my $file = $config->{'slv'}->{'logdir'} . '/' . (defined($tld) ? "$tld-" : '') . __script() . '.log';

    open $OUTFILE, '>>', $file or die("cannot open $file: $!");

    print {$OUTFILE} $msg or die("cannot write to $file: $!");

    close $OUTFILE or die("cannot close $file: $!");
}

sub __get_macro
{
    my $m = shift;

    my $res = db_select("select value from globalmacro where macro='$m'");

    my $arr_ref = $res->fetchall_arrayref();

    fail("cannot find macro '$m'") unless (1 == scalar(@$arr_ref));

    return $arr_ref->[0]->[0];
}

# organize values from all hosts grouped by name server and return "name server"->values hash
#
# E. g.:
#
# 'g.ns.se.,2001:6b0:e:3::1' => [
#          205
#    ];
# 'b.ns.se.,192.36.133.107' => [
#          -102
#          304
#    ];
sub __get_ns_values
{
    my $items_ref = shift;
    my $from = shift;
    my $till = shift;
    my $all_ns_items = shift;

    my %result;

    if (0 < scalar(keys(%$items_ref)))
    {
	my $items_str = "";
	foreach my $itemid (keys(%$items_ref))
	{
	    $items_str .= "," unless ($items_str eq "");
	    $items_str .= $itemid;
	}

	my $res = db_select("select itemid,value from history where itemid in ($items_str) and clock between $from and $till");

	while (my @row = $res->fetchrow_array)
	{
	    my $itemid = $row[0];
	    my $value = $row[1];

	    my $ns = '';
	    my $last = 0;
	    foreach my $hostid (keys(%$all_ns_items))
	    {
		foreach my $i (keys(%{$all_ns_items->{$hostid}}))
		{
		    if ($i == $itemid)
		    {
			$ns = $all_ns_items->{$hostid}{$i};
			$last = 1;
			last;
		    }
		}
		last if ($last == 1);
	    }

	    fail("internal error: name server of item $itemid not found") if ($ns eq "");

	    if (exists($result{$ns}))
	    {
		$result{$ns} = [@{$result{$ns}}, $value];
	    }
	    else
	    {
		$result{$ns} = [$value];
	    }
	}
    }

    return \%result;
}

# return an array reference of values of items for the particular period
sub __get_dbl_values
{
    my $items_ref = shift;
    my $from = shift;
    my $till = shift;

    my @result;

    if (0 < scalar(keys(%$items_ref)))
    {
	my $items_str = "";
	foreach my $itemid (keys(%$items_ref))
	{
	    $items_str .= "," unless ($items_str eq "");
	    $items_str .= $itemid;
	}

	my $res = db_select("select value from history where itemid in ($items_str) and clock between $from and $till");

	while (my @row = $res->fetchrow_array)
	{
	    push(@result, $row[0]);
	}
    }

    return \@result;
}

sub __get_online_items
{
    my $hostids_ref = shift;
    my $all_items = shift;

    my %result;

    foreach my $hostid (@$hostids_ref)
    {
	fail("internal error: no hostid $hostid in input items") unless ($all_items->{$hostid});

	foreach my $itemid (keys(%{$all_items->{$hostid}}))
	{
	    $result{$itemid} = $all_items->{$hostid}{$itemid};
	}
    }

    return \%result;
}

# return items hash from all hosts grouped by hostid (hostid => hash of its items (itemid => ns)):
#
# hostid1
#   32389 => 'i.ns.se.,2001:67c:1010:5::53',
#   32386 => 'g.ns.se.,130.239.5.114',
#   ...
# hostid2
#   ...
# ...
sub __get_all_ns_items
{
    my $nss_ref = shift; # array reference of name servers ("name,IP")
    my $cfg_key_in = shift;
    my $tld = shift;

    my @keys;
    push(@keys, "'" . $cfg_key_in . $_ . "]'") foreach (@$nss_ref);

    my $keys_str = join(',', @keys);

    my $res = db_select(
	"select h.hostid,i.itemid,i.key_,h.host ".
	"from items i,hosts h ".
	"where i.hostid=h.hostid".
		" and h.host like '$tld %'".
		" and i.templateid is not null".
		" and i.key_ in ($keys_str)");

    my %all_ns_items;
    while (my @row = $res->fetchrow_array)
    {
	$all_ns_items{$row[0]}{$row[1]} = get_ns_from_key($row[2]);
    }

    fail("cannot find items ($keys_str) at host ($tld *)") if (scalar(keys(%all_ns_items)) == 0);

    return \%all_ns_items;
}

# return items hash from all hosts (hostid => hash of its item (itemid => '')):
#
# hostid1
#   32389 => ''
# hostid2
#   32419 => ''
# ...
sub __get_all_items
{
    my $key = shift;

    my $res = db_select(
	"select h.hostid,i.itemid".
	" from items i,hosts h".
	" where i.hostid=h.hostid".
		" and i.templateid is null".
		" and i.key_='$key'");

    my %all_items;
    while (my @row = $res->fetchrow_array)
    {
	$all_items{$row[0]}{$row[1]} = '';
    }

    fail("no items matching '$key' found in the database") if (scalar(keys(%all_items)) == 0);

    return \%all_items;
}

# get array of key nameservers ('i.ns.se.,130.239.5.114', ...)
sub __get_nss
{
    my $tld = shift;
    my $cfg_key_out = shift;

    my $res = db_select("select key_ from items i,hosts h where i.hostid=h.hostid and h.host='$tld' and i.key_ like '$cfg_key_out%'");

    my @nss;
    while (my @row = $res->fetchrow_array)
    {
	push(@nss, get_ns_from_key($row[0]));
    }

    fail("cannot find items ($cfg_key_out*) at host ($tld)") if (scalar(@nss) == 0);

    return \@nss;
}

sub __script
{
    my $script = $0;

    $script =~ s,.*/([^/]*)$,$1,;

    return $script;
}

sub __get_pidfile
{
    return $pid_dir . '/' . __script() . '.pid';
}

# return incidents (start and end times) in array:
#
# [
#   1386066210, 1386340110,
#   1386340290, 1386340470
# ]
#
# if the incident is still onoing at the passed $from time that time will be used
# as end time
sub __get_eventtimes
{
    my $itemid = shift;
    my $from = shift;
    my $till = shift;

    my $res = db_select(
	"select distinct t.triggerid".
	" from triggers t,functions f".
	" where t.triggerid=f.triggerid".
		" and f.itemid=$itemid".
		" and t.priority=".TRIGGER_SEVERITY_NOT_CLASSIFIED);

    my $arr_ref = $res->fetchall_arrayref();

    my $rows = scalar(@$arr_ref);

    my @eventtimes;

    unless ($rows == 1)
    {
	wrn("item $itemid must have one not classified trigger (found: $rows)");
	return \@eventtimes;
    }

    my $triggerid = $arr_ref->[0]->[0];

    # select events, where time_from < filter_from and value = TRIGGER_VALUE_TRUE
    $res = db_select(
	"select clock,value,false_positive".
	" from events".
	" where object=".EVENT_OBJECT_TRIGGER.
		" and source=".EVENT_SOURCE_TRIGGERS.
		" and objectid=$triggerid".
		" and clock<$from".
		" and value_changed=".TRIGGER_VALUE_CHANGED_YES.
	" order by clock,ns desc".
	" limit 1");

    while (my @row = $res->fetchrow_array)
    {
	my $clock = $row[0];
	my $value = $row[1];
	my $false_positive = $row[2];

	# we cannot add 'value=TRIGGER_VALUE_TRUE' and 'false_positive!=INCIDENT_FALSE_POSITIVE'
	# to the SQL query above as we need the latest event before $from
	push(@eventtimes, $clock) if ($value == TRIGGER_VALUE_TRUE and $false_positive != INCIDENT_FALSE_POSITIVE);
    }

    $res = db_select(
	"select clock,value,false_positive".
	" from events".
	" where object=".EVENT_OBJECT_TRIGGER.
		" and source=".EVENT_SOURCE_TRIGGERS.
		" and objectid=$triggerid".
		" and value_changed=".TRIGGER_VALUE_CHANGED_YES.
		" and clock between $from and $till".
	" order by clock,ns");

    my (@unsorted_eventtimes, @row, $add_event);
    $add_event = 1;
    while (@row = $res->fetchrow_array)
    {
	my $clock = $row[0];
	my $value = $row[1];
	my $false_positive = $row[2];

	if ($value == TRIGGER_VALUE_TRUE)
	{
	    $add_event = ($false_positive == INCIDENT_FALSE_POSITIVE) ? 0 : 1;
	}

	push(@unsorted_eventtimes, $clock) if ($add_event == 1);
    }

    push(@eventtimes, $_) foreach (sort(@unsorted_eventtimes));
    push(@eventtimes, $till) unless ((scalar(@eventtimes) % 2) == 0);

    return \@eventtimes;
}

# Returns a reference to hash of all probes (host name => hostid).
sub __get_probes
{
    my $res = db_select(
	"select h.host,h.hostid".
	" from hosts h, hosts_groups hg, groups g".
	" where h.hostid=hg.hostid".
		" and hg.groupid=g.groupid".
		" and g.name='$probe_group_name'");

    my (%result, @row);
    while (@row = $res->fetchrow_array)
    {
	$result{$row[0]} = $row[1];
    }

    return \%result;
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

    while (not $value and $diff < $month)
    {
	my $res = db_select(
	    "select h.value".
	    " from items i,history_uint h".
	    " where i.itemid=h.itemid".
	    	" and i.key_='$item_prefix.configvalue[$item_param]'".
	    	" and h.clock between " . ($value_time - $diff) . " and $value_time".
	    " order by h.clock DESC".
	    " limit 1");

	while (my @row = $res->fetchrow_array)
	{
	    $value = $row[0];
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
