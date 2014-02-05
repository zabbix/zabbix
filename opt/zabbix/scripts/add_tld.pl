#!/usr/bin/perl
#
# - DNS availability test		(data collection)	dnstest.dns.udp			(simple, every minute)
#								dnstest.dns.tcp			(simple, every 50 minutes)
#								dnstest.dns.udp.rtt		(trapper)
#								dnstest.dns.tcp.rtt		-|-
#								dnstest.dns.udp.upd		-|-
# - RDDS availability test		(data collection)	dnstest.rdds			(simple, every minutes)
#   (also RDDS43 and RDDS80					dnstest.rdds.43.ip		(trapper)
#   availability at a particular				dnstest.rdds.43.rtt		-|-
#   minute)							dnstest.rdds.43.upd		-|-
#								dnstest.rdds.80.ip		-|-
#								dnstest.rdds.80.rtt		-|-
#
# - DNS NS availability			(given minute)		dnstest.slv.dns.ns.avail	-|-	+
# - DNS NS monthly availability		(monthly)		dnstest.slv.dns.ns.month	-|-	+
# - DNS monthly resolution RTT		(monthly)		dnstest.slv.dns.ns.rtt.udp.month-|-	+
# - DNS monthly resolution RTT (TCP)	(monthly, TCP)		dnstest.slv.dns.ns.rtt.tcp.month-|-	+
# - DNS monthly update time		(monthly)		dnstest.slv.dns.ns.upd.month	-|-	+
# - DNS availability			(given minute)		dnstest.slv.dns.avail		-|-	+
# - DNS rolling week			(rolling week)		dnstest.slv.dns.rollweek	-|-	+
# - DNSSEC proper resolution		(given minute)		dnstest.slv.dnssec.avail	-|-	+
# - DNSSEC rolling week			(rolling week)		dnstest.slv.dnssec.rollweek	-|-	+
#
# - RDDS availability			(given minute)		dnstest.slv.rdds.avail		-|-	-
# - RDDS rolling week			(rolling week)		dnstest.slv.rdds.rollweek	-|-	-
# - RDDS43 monthly resolution RTT	(monthly)		dnstest.slv.rdds.43.rtt.month	-|-	-
# - RDDS80 monthly resolution RTT	(monthly)		dnstest.slv.rdds.80.rtt.month	-|-	-
# - RDDS monthly update time		(monthly)		dnstest.slv.rdds.upd.month	-|-	-
#

use lib '/opt/zabbix/scripts';

use strict;
use warnings;
use Zabbix;
use LWP::Simple qw(get);
use Getopt::Long;
use Data::Dumper;
use DNSTest;

my $VALUE_TYPE_AVAIL = 0;
my $VALUE_TYPE_PERC = 1;

my $ZBX_EC_DNS_NS_NOREPLY    = -200; # no reply from Name Server
my $ZBX_EC_DNS_NS_ERRREPLY   = -201; # invalid reply from Name Server
my $ZBX_EC_DNS_NS_NOTS       = -202; # no UNIX timestamp
my $ZBX_EC_DNS_NS_ERRTS      = -203; # invalid UNIX timestamp
my $ZBX_EC_DNS_NS_ERRSIG     = -204; # DNSSEC error
my $ZBX_EC_DNS_RES_NOREPLY   = -205; # no reply from resolver
my $ZBX_EC_DNS_RES_NOADBIT   = -206; # no AD bit in the answer from resolver
my $ZBX_EC_RDDS43_NOREPLY    = -200; # no reply from RDDS43 server
my $ZBX_EC_RDDS43_NONS       = -201; # Whois server returned no NS
my $ZBX_EC_RDDS43_NOTS       = -202; # no Unix timestamp
my $ZBX_EC_RDDS43_ERRTS      = -203; # invalid Unix timestamp
my $ZBX_EC_RDDS80_NOREPLY    = -204; # no reply from RDDS80 server
my $ZBX_EC_RDDS_ERRRES       = -205; # cannot resolve a Whois host
my $ZBX_EC_RDDS80_NOHTTPCODE = -206; # no HTTP response code in response from RDDS80 server
my $ZBX_EC_RDDS80_EHTTPCODE  = -207; # invalid HTTP response code in response from RDDS80 server
my $ZBX_EC_EPP_NO_IP         = -200; # IP is missing for EPP server
my $ZBX_EC_EPP_CONNECT       = -201; # cannot connect to EPP server
my $ZBX_EC_EPP_CRYPT         = -202; # invalid certificate or private key
my $ZBX_EC_EPP_FIRSTTO       = -203; # first message timeout
my $ZBX_EC_EPP_FIRSTINVAL    = -204; # first message is invalid
my $ZBX_EC_EPP_LOGINTO       = -205; # LOGIN command timeout
my $ZBX_EC_EPP_LOGININVAL    = -206; # invalid reply to LOGIN command
my $ZBX_EC_EPP_UPDATETO      = -207; # UPDATE command timeout
my $ZBX_EC_EPP_UPDATEINVAL   = -208; # invalid reply to UPDATE command
my $ZBX_EC_EPP_INFOTO        = -209; # INFO command timeout
my $ZBX_EC_EPP_INFOINVAL     = -210; # invalid reply to INFO command

my $cfg_probe_status_delay = 60;
my $cfg_default_rdds_ns_string = "Name Server:";

my $dnstest_host = "dnstest"; # global config history
my $dnstest_group = "dnstest";

my %OPTS;
my $args = GetOptions(\%OPTS,
		      "tld=s",
		      "rdds43-servers=s",
		      "rdds80-servers=s",
		      "dns-test-prefix=s",
		      "rdds-test-prefix=s",
		      "ipv4!",
		      "ipv6!",
		      "dnssec!",
		      "epp-server=s",
		      "ns-servers-v4=s",
		      "ns-servers-v6=s",
		      "rdds-ns-string=s",
		      "only-cron!",
		      "verbose!",
		      "quiet!",
		      "help|?");

usage() if ($OPTS{'help'});

validate_input();
lc_options();

# TODO: add command-line parameters:
# - rdds43 hosts (2nd parameter of dnstest.rdds)
# - rdds80 hosts (3rd parameter of dnstest.rdds)
# ...

my $main_templateid;
my $ns_servers;

use constant value_mappings => {'dnstest_dns' => 13,
				'dnstest_probe' => 14,
				'dnstest_rdds_rttudp' => 15,
				'dnstest_avail' => 16,
				'dnstest_rdds_avail' => 18,
                                'dnstest_epp' => 19};

use constant APP_SLV_MONTHLY => 'SLV monthly';
use constant APP_SLV_ROLLWEEK => 'SLV rolling week';
use constant APP_SLV_PARTTEST => 'SLV particular test';

my $config = get_dnstest_config();
my $zabbix = Zabbix->new({'url' => $config->{'zapi'}->{'url'}, user => $config->{'zapi'}->{'user'}, password => $config->{'zapi'}->{'password'}});

my $proxies = get_proxies_list();

pfail("cannot find existing proxies") if (scalar(keys %{$proxies}) == 0);

create_cron_items();
exit if (defined($OPTS{'only-cron'}));

create_macro('{$DNSTEST.IP4.MIN.SERVERS}', 4, undef);
create_macro('{$DNSTEST.IP6.MIN.SERVERS}', 4, undef);
create_macro('{$DNSTEST.IP4.REPLY.MS}', 200, undef);
create_macro('{$DNSTEST.IP6.REPLY.MS}', 200, undef);

create_macro('{$DNSTEST.DNS.TCP.RTT}', 1500, undef);
create_macro('{$DNSTEST.DNS.UDP.RTT}', 500, undef);
create_macro('{$DNSTEST.DNS.UDP.DELAY}', 60, undef);
create_macro('{$DNSTEST.DNS.TCP.DELAY}', 3000, undef);
create_macro('{$DNSTEST.DNS.UPDATE.TIME}', 3600, undef);
create_macro('{$DNSTEST.DNS.PROBE.ONLINE}', 20, undef);
create_macro('{$DNSTEST.DNS.AVAIL.MINNS}', 2, undef);
create_macro('{$DNSTEST.DNS.ROLLWEEK.SLA}', 240, undef);

create_macro('{$DNSTEST.RDDS.RTT}', 2000, undef);
create_macro('{$DNSTEST.RDDS.DELAY}', 300, undef);
create_macro('{$DNSTEST.RDDS.UPDATE.TIME}', 3600, undef);
create_macro('{$DNSTEST.RDDS.PROBE.ONLINE}', 10, undef);
create_macro('{$DNSTEST.RDDS.ROLLWEEK.SLA}', 48, undef);
create_macro('{$DNSTEST.RDDS.MAXREDIRS}', 10, undef);

create_macro('{$DNSTEST.EPP.DELAY}', 300, undef);
create_macro('{$DNSTEST.EPP.LOGIN.RTT}', 4000, undef);
create_macro('{$DNSTEST.EPP.UPDATE.RTT}', 4000, undef);
create_macro('{$DNSTEST.EPP.INFO.RTT}', 2000, undef);
create_macro('{$DNSTEST.EPP.PROBE.ONLINE}', 5, undef);
create_macro('{$DNSTEST.EPP.ROLLWEEK.SLA}', 48, undef);

create_macro('{$DNSTEST.PROBE.ONLINE.DELAY}', 180, undef);

create_macro('{$DNSTEST.TRIG.DOWNCOUNT}', '#1', undef);
create_macro('{$DNSTEST.TRIG.UPCOUNT}', '#3', undef);

create_macro('{$INCIDENT.DNS.FAIL}', 3, undef);
create_macro('{$INCIDENT.DNS.RECOVER}', 3, undef);
create_macro('{$INCIDENT.DNSSEC.FAIL}', 3, undef);
create_macro('{$INCIDENT.DNSSEC.RECOVER}', 3, undef);
create_macro('{$INCIDENT.RDDS.FAIL}', 2, undef);
create_macro('{$INCIDENT.RDDS.RECOVER}', 2, undef);
create_macro('{$INCIDENT.EPP.FAIL}', 2, undef);
create_macro('{$INCIDENT.EPP.RECOVER}', 2, undef);

create_macro('{$DNSTEST.SLV.DNS.UDP.RTT}', 99, undef);
create_macro('{$DNSTEST.SLV.DNS.TCP.RTT}', 99, undef);
create_macro('{$DNSTEST.SLV.NS.AVAIL}', 99, undef);
create_macro('{$DNSTEST.SLV.RDDS43.RTT}', 99, undef);
create_macro('{$DNSTEST.SLV.RDDS80.RTT}', 99, undef);
create_macro('{$DNSTEST.SLV.RDDS.UPD}', 99, undef);
create_macro('{$DNSTEST.SLV.DNS.NS.UPD}', 99, undef);
create_macro('{$DNSTEST.SLV.EPP.LOGIN}', 99, undef);
create_macro('{$DNSTEST.SLV.EPP.UPDATE}', 99, undef);
create_macro('{$DNSTEST.SLV.EPP.INFO}', 99, undef);

create_macro('{$ROLLING.WEEK.STATUS.PAGE.SLV}', '0,5,10,25,50,75,100', undef);

my $result;
my $m = '{$DNSTEST.DNS.UDP.DELAY}';
unless (($result = $zabbix->get('usermacro', {'globalmacro' => 1, output => 'extend', 'filter' => {'macro' => $m}}))
	and defined $result->{'value'}) {
    pfail('cannot get macro ', $m);
}
my $cfg_dns_udp_delay = $result->{'value'};
$m = '{$DNSTEST.DNS.TCP.DELAY}';
unless (($result = $zabbix->get('usermacro', {'globalmacro' => 1, output => 'extend', 'filter' => {'macro' => $m}}))
	and defined $result->{'value'}) {
    pfail('cannot get macro ', $m);
}
my $cfg_dns_tcp_delay = $result->{'value'};
$m = '{$DNSTEST.RDDS.DELAY}';
unless (($result = $zabbix->get('usermacro', {'globalmacro' => 1, output => 'extend', 'filter' => {'macro' => $m}}))
	and defined $result->{'value'}) {
    pfail('cannot get macro ', $m);
}
my $cfg_rdds_delay = $result->{'value'};
$m = '{$DNSTEST.EPP.DELAY}';
unless (($result = $zabbix->get('usermacro', {'globalmacro' => 1, output => 'extend', 'filter' => {'macro' => $m}}))
	         and defined $result->{'value'}) {
        pfail('cannot get macro ', $m);
    }
my $cfg_epp_delay = $result->{'value'};

my $dnstest_groupid = create_group($dnstest_group);

my $dnstest_hostid = create_host({'groups' => [{'groupid' => $dnstest_groupid}],
			      'host' => $dnstest_host,
			      'interfaces' => [{'type' => 1, 'main' => 1, 'useip' => 1, 'ip'=> '127.0.0.1', 'dns' => '', 'port' => '10050'}]});

# calculated items, configuration history (TODO: rename host to something like config_history)
create_dnstest_items($dnstest_hostid);

$ns_servers = get_ns_servers($OPTS{'tld'});

my $root_servers_macros = update_root_servers();

$main_templateid = create_main_template($OPTS{'tld'}, $ns_servers);

my $tld_groupid = create_group('TLD '.$OPTS{'tld'});

my $tlds_groupid = create_group('TLDs');

my $tld_hostid = create_host({'groups' => [{'groupid' => $tld_groupid}, {'groupid' => $tlds_groupid}],
			      'host' => $OPTS{'tld'},
			      'interfaces' => [{'type' => 1, 'main' => 1, 'useip' => 1, 'ip'=> '127.0.0.1', 'dns' => '', 'port' => '10050'}]});

create_slv_items($ns_servers, $tld_hostid, $OPTS{'tld'});

my $probes_groupid = create_group('Probes');

my $probes_mon_groupid = create_group('Probes - Mon');

my $proxy_mon_templateid = create_template('Template Proxy Health');

foreach my $proxyid (sort keys %{$proxies}) {
    my $probe_name = $proxies->{$proxyid}->{'host'};

    print $proxyid."\n";
    print $proxies->{$proxyid}->{'host'}."\n";

    my $proxy_groupid = create_group($probe_name);

    my $probe_templateid = create_probe_template($probe_name);
    my $probe_status_templateid = create_probe_status_template($probe_name, $probe_templateid);

    create_host({'groups' => [{'groupid' => $proxy_groupid}, {'groupid' => $probes_groupid}],
                                          'templates' => [{'templateid' => $probe_status_templateid}],
                                          'host' => $probe_name,
                                          'proxy_hostid' => $proxyid,
                                          'interfaces' => [{'type' => 1, 'main' => 1, 'useip' => 1,
							    'ip'=> '127.0.0.1',
							    'dns' => '', 'port' => '10050'}]
		});

    my $hostid = create_host({'groups' => [{'groupid' => $probes_mon_groupid}],
                                          'templates' => [{'templateid' => $proxy_mon_templateid}],
                                          'host' => $probe_name.' - mon',
                                          'interfaces' => [{'type' => 1, 'main' => 1, 'useip' => 1,
                                                            'ip'=> $proxies->{$proxyid}->{'interfaces'}[0]->{'ip'},
                                                            'dns' => '', 'port' => '10050'}]
            		    });

    create_macro('{$DNSTEST.PROXY_NAME}', $probe_name, $hostid, 1);

    create_host({'groups' => [{'groupid' => $tld_groupid}, {'groupid' => $proxy_groupid}],
                                          'templates' => [{'templateid' => $main_templateid}, {'templateid' => $probe_templateid}],
                                          'host' => $OPTS{'tld'}.' '.$probe_name,
                                          'proxy_hostid' => $proxyid,
                                          'interfaces' => [{'type' => 1, 'main' => 1, 'useip' => 1, 'ip'=> '127.0.0.1', 'dns' => '', 'port' => '10050'}]});
}

create_probe_status_host($probes_mon_groupid);

########### FUNCTIONS ###############

sub create_host {
    my $options = shift;

    unless ($zabbix->exist('host',{'name' => $options->{'host'}})) {
	print("creating host '", $options->{'host'}, "'\n");
        my $result = $zabbix->create('host', $options);

        return $result->{'hostids'}[0];
    }

    my $result = $zabbix->get('host', {'output' => ['hostid'], 'filter' => {'host' => [$options->{'host'}]}});

    pfail("more than one host named \"", $options->{'host'}, "\" found") if ('ARRAY' eq ref($result));
    pfail("host \"", $options->{'host'}, "\" not found") unless (defined($result->{'hostid'}));

    $options->{'hostid'} = $result->{'hostid'};
    delete($options->{'interfaces'});
    $result = $zabbix->update('host', $options);

    my $hostid = $result->{'hostid'} ? $result->{'hostid'} : $options->{'hostid'};

    return $hostid;
}

sub get_application_id {
    my $name = shift;
    my $templateid = shift;

    unless ($zabbix->exist('application',{'name' => $name, 'hostid' => $templateid})) {
	my $result = $zabbix->create('application', {'name' => $name, 'hostid' => $templateid});
	return $result->{'applicationids'}[0];
    }

    my $result = $zabbix->get('application', {'hostids' => [$templateid], 'filter' => {'name' => $name}});
    return $result->{'applicationid'};
}

sub get_proxies_list {
    my $proxies_list;

    $proxies_list = $zabbix->get('proxy',{'output' => ['proxyid', 'host'], 'selectInterfaces' => ['ip'], 'preservekeys' => 1 });

    return $proxies_list;
}

sub get_ns_servers {
    my $tld = shift;

    if ($OPTS{'ns-servers-v4'} or $OPTS{'ns-servers-v6'}) {
	if ($OPTS{'ns-servers-v4'} and $OPTS{'ipv4'} == 1) {
	    my @nsservers = split(/\s/, $OPTS{'ns-servers-v4'});
	    foreach my $ns (@nsservers) {
		my @entries = split(/,/, $ns);

		my $exists = 0;
		foreach my $ip (@{$ns_servers->{$entries[0]}{'v4'}}) {
		    if ($ip eq $entries[1]) {
			$exists = 1;
			last;
		    }
		}

		push(@{$ns_servers->{$entries[0]}{'v4'}}, $entries[1]) unless ($exists);
	    }
	}

	if ($OPTS{'ns-servers-v6'} and $OPTS{'ipv6'} == 1) {
	    my @nsservers = split(/\s/, $OPTS{'ns-servers-v6'});
	    foreach my $ns (@nsservers) {
		my @entries = split(/,/, $ns);

		my $exists = 0;
		foreach my $ip (@{$ns_servers->{$entries[0]}{'v6'}}) {
		    if ($ip eq $entries[1]) {
			$exists = 1;
			last;
		    }
		}

		push(@{$ns_servers->{$entries[0]}{'v6'}}, $entries[1]) unless ($exists);
	    }
	}
    } else {
	my $nsservers = `dig $tld NS +short`;
	my @nsservers = split(/\n/,$nsservers);

	foreach (my $i = 0;$i<=$#nsservers; $i++) {
	    if ($OPTS{'ipv4'} == 1) {
		my $ipv4 = `dig $nsservers[$i] A +short`;
		my @ipv4 = split(/\n/, $ipv4);

		@{$ns_servers->{$nsservers[$i]}{'v4'}} = @ipv4 if scalar @ipv4;
	    }

	    if ($OPTS{'ipv6'} == 1) {
		my $ipv6 = `dig $nsservers[$i] AAAA +short` if $OPTS{'ipv6'};
		my @ipv6 = split(/\n/, $ipv6);

		@{$ns_servers->{$nsservers[$i]}{'v6'}} = @ipv6 if scalar @ipv6;
	    }
	}
    }

    return $ns_servers;
}

sub create_group {
    my $name = shift;

    my $groupid;

    unless ($zabbix->exist('hostgroup',{'name' => $name})) {
        my $result = $zabbix->create('hostgroup', {'name' => $name});
	$groupid = $result->{'groupids'}[0];
    }
    else {
	my $result = $zabbix->get('hostgroup', {'filter' => {'name' => [$name]}});
        $groupid = $result->{'groupid'};
    }

    return $groupid;
}

sub create_template {
    my $name = shift;
    my $child_templateid = shift;

    my ($result, $templateid, $options);

    unless ($zabbix->exist('template',{'host' => $name})) {
        $result = $zabbix->get('hostgroup', {'filter' => {'name' => 'Templates'}});

	$options = {'groups'=> {'groupid' => $result->{'groupid'}}, 'host' => $name};

	$options->{'templates'} = [{'templateid' => $child_templateid}] if defined $child_templateid;

	$result = $zabbix->create('template', $options);
        $templateid = $result->{'templateids'}[0];
    }
    else {
	$result = $zabbix->get('template', {'filter' => {'host' => $name}});
	$templateid = $result->{'templateid'};
	$result = $zabbix->get('hostgroup', {'filter' => {'name' => 'Templates'}});

	$options = {'templateid' => $templateid, 'groups'=> {'groupid' => $result->{'groupid'}}, 'host' => $name};
	$options->{'templates'} = [{'templateid' => $child_templateid}] if defined $child_templateid;

	$result = $zabbix->update('template', $options);
	$templateid = $result->{'templateids'}[0];
    }

    return $templateid;
}

sub create_item {
    my $options = shift;
    my $result;

    pfail("cannot add item: hostid not specified\n", Dumper($options)) unless (defined($options->{'hostid'}));

    if ($zabbix->exist('item', {'hostid' => $options->{'hostid'}, 'key_' => $options->{'key_'}})) {
	$result = $zabbix->get('item', {'hostids' => $options->{'hostid'}, 'filter' => {'key_' => $options->{'key_'}}});

	if ('ARRAY' eq ref($result))
	{
	    pfail("Request: ", Dumper($options),
		  "returned more than one item with key ", $options->{'key_'}, ":\n",
		  Dumper($result));
	}

	$options->{'itemid'} = $result->{'itemid'};

	$result = $zabbix->update('item', $options);
    }
    else {
        $result = $zabbix->create('item', $options);
    }

    $result = ${$result->{'itemids'}}[0] if (defined(${$result->{'itemids'}}[0]));

    pfail("cannot create item:\n", Dumper($options)) if (ref($result) ne '' or $result eq '');

    return $result;
}

sub create_trigger {
    my $options = shift;
    my $result;

    if ($zabbix->exist('trigger',{'expression' => $options->{'expression'}})) {
        $result = $zabbix->update('trigger', $options);
    }
    else {
        $result = $zabbix->create('trigger', $options);
    }

    return $result;
}

sub create_item_dns_rtt {
    my $ns_name = shift;
    my $ip = shift;
    my $templateid = shift;
    my $template_name = shift;
    my $proto = shift;

    pfail("undefined template ID passed to create_item_dns_rtt()") unless ($templateid);
    pfail("no protocol parameter specified to create_item_dns_rtt()") unless ($proto);

    my $proto_lc = lc($proto);
    my $proto_uc = uc($proto);

    my $item_key = 'dnstest.dns.'.$proto_lc.'.rtt[{$DNSTEST.TLD},'.$ns_name.','.$ip.']';

    my $options = {'name' => 'DNS RTT of $2 ($3) ('.$proto_uc.')',
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [get_application_id('DNS RTT ('.$proto_uc.')', $templateid)],
                                              'type' => 2, 'value_type' => 0,
                                              'valuemapid' => value_mappings->{'dnstest_dns'}};

    create_item($options);

    $options = { 'description' => 'No reply from Name Server '.$ns_name.' ['.$ip.'] on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_NS_NOREPLY,
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => 'Invalid reply from Name Server '.$ns_name.' ['.$ip.'] on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_NS_ERRREPLY,
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => 'UNIXTIME is missing from '.$ns_name.' ['.$ip.'] on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_NS_NOTS,
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => 'Invalid UNIXTIME from '.$ns_name.' ['.$ip.'] on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_NS_ERRTS,
                        'priority' => '2',
                };

    create_trigger($options);

    if (defined($OPTS{'dnssec'})) {
	$options = { 'description' => '5.1.1 Step 7 - DNSSEC error from '.$ns_name.' ['.$ip.'] on {HOST.NAME} ('.$proto_uc.')',
		     'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_NS_ERRSIG,
		     'priority' => '2',
	};

	create_trigger($options);
    }

    $options = { 'description' => 'No reply from resolver on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_RES_NOREPLY,
			'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => '5.1.1 Step 2 - ad bit is missing from '.$ns_name.' ['.$ip.'] on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_RES_NOADBIT,
			'priority' => '2',
                };

    create_trigger($options);

    return;
}

sub create_slv_item {
    my $name = shift;
    my $key = shift;
    my $hostid = shift;
    my $value_type = shift;
    my $applicationids = shift;

    my $options;
    if ($value_type == $VALUE_TYPE_AVAIL)
    {
	$options = {'name' => $name,
                                              'key_'=> $key,
                                              'hostid' => $hostid,
                                              'type' => 2, 'value_type' => 3,
					      'applications' => $applicationids,
					      'valuemapid' => value_mappings->{'dnstest_avail'}};
    }
    elsif ($value_type == $VALUE_TYPE_PERC) {
	$options = {'name' => $name,
                                              'key_'=> $key,
                                              'hostid' => $hostid,
                                              'type' => 2, 'value_type' => 0,
                                              'applications' => $applicationids,
					      'units' => '%'};
    }
    else {
	pfail("Unknown value type $value_type.");
    }

    return create_item($options);
}

sub create_item_dns_udp_upd {
    my $ns_name = shift;
    my $ip = shift;
    my $templateid = shift;

    my $proto_uc = 'UDP';

    my $options = {'name' => 'DNS update time of $2 ($3)',
                                              'key_'=> 'dnstest.dns.udp.upd[{$DNSTEST.TLD},'.$ns_name.','.$ip.']',
                                              'hostid' => $templateid,
                                              'applications' => [get_application_id('DNS RTT ('.$proto_uc.')', $templateid)],
                                              'type' => 2, 'value_type' => 0,
                                              'valuemapid' => value_mappings->{'dnstest_dns'},
		                              'status' => (defined($OPTS{'epp-server'}) ? 0 : 1)};
    return create_item($options);
}

sub create_items_dns {
    my $templateid = shift;
    my $template_name = shift;

    my $proto = 'tcp';
    my $proto_uc = uc($proto);
    my $item_key = 'dnstest.dns.'.$proto.'[{$DNSTEST.TLD}]';

    my $options = {'name' => 'Number of working DNS Name Servers of $1 ('.$proto_uc.')',
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [get_application_id('DNS ('.$proto_uc.')', $templateid)],
                                              'type' => 3, 'value_type' => 3,
                                              'delay' => $cfg_dns_tcp_delay, 'valuemapid' => value_mappings->{'dnstest_dns'}};

    create_item($options);

    $options = { 'description' => '5.2.3 - Less than {$DNSTEST.DNS.AVAIL.MINNS} NS servers have answered succesfully on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}<{$DNSTEST.DNS.AVAIL.MINNS}',
			'priority' => '4',
                };

    create_trigger($options);

    $options = { 'description' => 'No reply from Name Server on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_NS_NOREPLY,
		    'priority' => '3',
                };

    create_trigger($options);

    $options = { 'description' => 'Invalid reply from Name Server on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_NS_ERRREPLY,
			'priority' => '3',
                };

    create_trigger($options);

    $options = { 'description' => '5.1.1 Step 6 - UNIXTIME is missing on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_NS_NOTS,
		    'priority' => '3',
                };

    create_trigger($options);

    $options = { 'description' => '5.1.1 Step 6 - Invalid UNIXTIME on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_NS_ERRTS,
			'priority' => '3',
                };

    create_trigger($options);

    if (defined($OPTS{'dnssec'})) {
	$options = { 'description' => 'DNSSEC error on {HOST.NAME} ('.$proto_uc.')',
		     'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_NS_ERRSIG,
		     'priority' => '3',
	};

	create_trigger($options);
    }

    $options = { 'description' => 'No reply from resolver on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_RES_NOREPLY,
			'priority' => '3',
                };

    create_trigger($options);


    $options = { 'description' => '5.1.1 Step 2 - ad bit is missing on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_RES_NOADBIT,
			'priority' => '3',
                };

    create_trigger($options);

    $proto = 'udp';
    $proto_uc = uc($proto);
    $item_key = 'dnstest.dns.'.$proto.'[{$DNSTEST.TLD}]';

    $options = {'name' => 'Number of working DNS Name Servers of $1 ('.$proto_uc.')',
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [get_application_id('DNS ('.$proto_uc.')', $templateid)],
                                              'type' => 3, 'value_type' => 3,
                                              'delay' => $cfg_dns_udp_delay, 'valuemapid' => value_mappings->{'dnstest_dns'}};

    create_item($options);

    $options = { 'description' => '5.2.3 - Less than {$DNSTEST.DNS.AVAIL.MINNS} NS servers have answered succesfully on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}<{$DNSTEST.DNS.AVAIL.MINNS}',
			'priority' => '4',
                };

    create_trigger($options);

    $options = { 'description' => 'No reply from Name Server on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_NS_NOREPLY,
		    'priority' => '3',
                };

    create_trigger($options);

    $options = { 'description' => 'Invalid reply from Name Server on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_NS_ERRREPLY,
			'priority' => '3',
                };

    create_trigger($options);

    $options = { 'description' => '5.1.1 Step 6 - UNIXTIME is missing on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_NS_NOTS,
		    'priority' => '3',
                };

    create_trigger($options);

    $options = { 'description' => '5.1.1 Step 6 - Invalid UNIXTIME on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_NS_ERRTS,
			'priority' => '3',
                };

    create_trigger($options);

    if (defined($OPTS{'dnssec'})) {
	$options = { 'description' => 'DNSSEC error on {HOST.NAME} ('.$proto_uc.')',
		     'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_NS_ERRSIG,
		     'priority' => '3',
	};

	create_trigger($options);
    }

    $options = { 'description' => 'No reply from resolver on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_RES_NOREPLY,
			'priority' => '3',
                };

    create_trigger($options);


    $options = { 'description' => '5.1.1 Step 2 - ad bit is missing on {HOST.NAME} ('.$proto_uc.')',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_DNS_RES_NOADBIT,
			'priority' => '3',
                };

    create_trigger($options);
}

sub create_items_rdds {
    my $templateid = shift;
    my $template_name = shift;

    my $applicationid_43 = get_application_id('RDDS43', $templateid);
    my $applicationid_80 = get_application_id('RDDS80', $templateid);

    my $item_key = 'dnstest.rdds.43.ip[{$DNSTEST.TLD}]';

    my $options = {'name' => 'RDDS43 IP of $1',
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [$applicationid_43],
                                              'type' => 2, 'value_type' => 1,
                                              'valuemapid' => value_mappings->{'dnstest_rdds_rttudp'}};
    create_item($options);

    $item_key = 'dnstest.rdds.43.rtt[{$DNSTEST.TLD}]';

    $options = {'name' => 'RDDS43 RTT of $1',
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [$applicationid_43],
                                              'type' => 2, 'value_type' => 0,
                                              'valuemapid' => value_mappings->{'dnstest_rdds_rttudp'}};
    create_item($options);

    $options = { 'description' => '6.1.1 Step 5 - No reply from RDDS43 server [{ITEM.LASTVALUE2}] on {HOST.NAME}',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_RDDS43_NOREPLY.
                        		    '|{'.$template_name.':dnstest.rdds.43.ip[{$DNSTEST.TLD}].str(dummy)}=1',
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => '6.1.1 Step 5 - RDDS43 server [{ITEM.LASTVALUE2}] output does not contain "{$DNSTEST.RDDS.NS.STRING}" on {HOST.NAME}',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_RDDS43_NONS.
				        '|{'.$template_name.':dnstest.rdds.43.ip[{$DNSTEST.TLD}].str(dummy)}=1',
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => '6.1.1 Step 6 - UNIXTIME is missing in reply from RDDS43 server [{ITEM.LASTVALUE2}] on {HOST.NAME}',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_RDDS43_NOTS.
                        		    '|{'.$template_name.':dnstest.rdds.43.ip[{$DNSTEST.TLD}].str(dummy)}=1',
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => '6.1.1 Step 6 - Invalid UNIXTIME in reply from RDDS43 server [{ITEM.LASTVALUE2}] on {HOST.NAME}',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_RDDS43_ERRTS.
                        		'|{'.$template_name.':dnstest.rdds.43.ip[{$DNSTEST.TLD}].str(dummy)}=1',
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => '6.1.1 Step 2 - Cannot resolve an RDDS43 host [{ITEM.LASTVALUE2}] on {HOST.NAME}',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_RDDS_ERRRES.
                        		'|{'.$template_name.':dnstest.rdds.43.ip[{$DNSTEST.TLD}].str(dummy)}=1',
                        'priority' => '2',
                };

    create_trigger($options);

    if (defined($OPTS{'epp-server'})) {
	$item_key = 'dnstest.rdds.43.upd[{$DNSTEST.TLD}]';

	$options = {'name' => 'RDDS43 update time of $1',
		    'key_'=> $item_key,
		    'hostid' => $templateid,
		    'applications' => [$applicationid_43],
		    'type' => 2, 'value_type' => 0,
		    'valuemapid' => value_mappings->{'dnstest_rdds_rttudp'},
		    'status' => (defined($OPTS{'epp-server'}) ? 0 : 1)};
	create_item($options);
    }

    $item_key = 'dnstest.rdds.80.ip[{$DNSTEST.TLD}]';

    $options = {'name' => 'RDDS80 IP of $1',
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [$applicationid_80],
                                              'type' => 2, 'value_type' => 1};
    create_item($options);

    $item_key = 'dnstest.rdds.80.rtt[{$DNSTEST.TLD}]';

    $options = {'name' => 'RDDS80 RTT of $1',
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [$applicationid_80],
                                              'type' => 2, 'value_type' => 0,
                                              'valuemapid' => value_mappings->{'dnstest_rdds_rttudp'}};
    create_item($options);

    $options = { 'description' => '6.1.1 Step 5 - No reply from RDDS80 server [{ITEM.LASTVALUE2}] on {HOST.NAME}',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_RDDS80_NOREPLY.
                        		    '|{'.$template_name.':dnstest.rdds.80.ip[{$DNSTEST.TLD}].str(dummy)}=1',
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => '6.1.1 Step 2 - Cannot resolve an RDDS80 host [{ITEM.LASTVALUE2}] on {HOST.NAME}',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_RDDS_ERRRES.
                        		'|{'.$template_name.':dnstest.rdds.80.ip[{$DNSTEST.TLD}].str(dummy)}=1',
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => '6.1.1 Step 2 - Cannot get HTTP response code from RDDS80 server [{ITEM.LASTVALUE2}] on {HOST.NAME}',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_RDDS80_NOHTTPCODE.
                        		    '|{'.$template_name.':dnstest.rdds.80.ip[{$DNSTEST.TLD}].str(dummy)}=1',
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => '6.1.1 Step 2 - Invalid HTTP response code from RDDS80 server [{ITEM.LASTVALUE2}] on {HOST.NAME}',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.$ZBX_EC_RDDS80_EHTTPCODE.
                        		'|{'.$template_name.':dnstest.rdds.80.ip[{$DNSTEST.TLD}].str(dummy)}=1',
                        'priority' => '2',
                };

    create_trigger($options);

    $item_key = 'dnstest.rdds[{$DNSTEST.TLD},"'.$OPTS{'rdds43-servers'}.'","'.$OPTS{'rdds80-servers'}.'"]';

    $options = {'name' => 'Number of working RDDS services (43, 80) of $1',
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [get_application_id('RDDS', $templateid)],
                                              'type' => 3, 'value_type' => 3,
					      'delay' => $cfg_rdds_delay,
                                              'valuemapid' => value_mappings->{'dnstest_rdds_avail'}};
    create_item($options);
}

sub create_items_epp {
    my $templateid = shift;
    my $template_name = shift;

    my $applicationid = get_application_id('EPP', $templateid);

    my ($item_key, $options);

    $item_key = 'dnstest.epp[{$DNSTEST.TLD},'.$OPTS{'epp-server'}.']';

    $options = {'name' => 'EPP service availability at $1 ($2)',
		'key_'=> $item_key,
		'hostid' => $templateid,
		'applications' => [$applicationid],
		'type' => 3, 'value_type' => 3,
		'delay' => $cfg_epp_delay, 'valuemapid' => value_mappings->{'dnstest_avail'}};

    create_item($options);

    $item_key = 'dnstest.epp.ip[{$DNSTEST.TLD}]';

    $options = {'name' => 'EPP IP of $1',
		'key_'=> $item_key,
		'hostid' => $templateid,
		'applications' => [$applicationid],
		'type' => 2, 'value_type' => 1};

    create_item($options);

    $item_key = 'dnstest.epp.rtt[{$DNSTEST.TLD},login]';

    $options = {'name' => 'EPP $2 command RTT of $1',
		'key_'=> $item_key,
		'hostid' => $templateid,
		'applications' => [$applicationid],
		'type' => 2, 'value_type' => 0,
		'valuemapid' => value_mappings->{'dnstest_epp'}};

    create_item($options);

    $item_key = 'dnstest.epp.rtt[{$DNSTEST.TLD},update]';

    $options = {'name' => 'EPP $2 command RTT of $1',
		'key_'=> $item_key,
		'hostid' => $templateid,
		'applications' => [$applicationid],
		'type' => 2, 'value_type' => 0,
		'valuemapid' => value_mappings->{'dnstest_epp'}};

    create_item($options);

    $item_key = 'dnstest.epp.rtt[{$DNSTEST.TLD},info]';

    $options = {'name' => 'EPP $2 command RTT of $1',
		'key_'=> $item_key,
		'hostid' => $templateid,
		'applications' => [$applicationid],
		'type' => 2, 'value_type' => 0,
		'valuemapid' => value_mappings->{'dnstest_epp'}};

    create_item($options);
}

sub create_macro {
    my $name = shift;
    my $value = shift;
    my $templateid = shift;
    my $force_update = shift;

    my $result;

    if (defined($templateid)) {
	if ($zabbix->get('usermacro',{'countOutput' => 1, 'hostids' => $templateid, 'filter' => {'macro' => $name}})) {
	    $result = $zabbix->get('usermacro',{'output' => 'hostmacroid', 'hostids' => $templateid, 'filter' => {'macro' => $name}} );
    	    $result = $zabbix->update('usermacro',{'hostmacroid' => $result->{'hostmacroid'}, 'value' => $value}) if defined $result->{'hostmacroid'} 
														     and defined($force_update);
	}
	else {
	    $result = $zabbix->create('usermacro',{'hostid' => $templateid, 'macro' => $name, 'value' => $value});
        }

	return $result->{'hostmacroids'}[0];
    }
    else {
	if ($zabbix->get('usermacro',{'countOutput' => 1, 'globalmacro' => 1, 'filter' => {'macro' => $name}})) {
            $result = $zabbix->get('usermacro',{'output' => 'globalmacroid', 'globalmacro' => 1, 'filter' => {'macro' => $name}} );
            $result = $zabbix->macro_global_update({'globalmacroid' => $result->{'globalmacroid'}, 'value' => $value}) if defined $result->{'globalmacroid'} 
															and defined($force_update);
        }
        else {
            $result = $zabbix->macro_global_create({'macro' => $name, 'value' => $value});
        }

	return $result->{'globalmacroids'}[0];
    }

}

sub create_probe_status_template {
    my $probe_name = shift;
    my $child_templateid = shift;

    my $template_name = 'Template '.$probe_name.' Status';

    my $templateid = create_template($template_name, $child_templateid);

    my $options = {'name' => 'Probe status ($1)',
                                              'key_'=> 'dnstest.probe.status[automatic,'.$root_servers_macros.']',
                                              'hostid' => $templateid,
                                              'applications' => [get_application_id('Probe status', $templateid)],
                                              'type' => 3, 'value_type' => 3, 'delay' => $cfg_probe_status_delay,
                                              'valuemapid' => value_mappings->{'dnstest_probe'}};

    create_item($options);

    $options = { 'description' => '8.3 - Long manual disable node {HOST.HOST}',
                         'expression' => '{'.$template_name.':dnstest.probe.status[manual].max({$IP.MAX.OFFLINE.MANUAL}h)}=0',
                        'priority' => '3',
                };

    create_trigger($options);


    $options = {'name' => 'Probe status ($1)',
                                              'key_'=> 'dnstest.probe.status[manual]',
                                              'hostid' => $templateid,
                                              'applications' => [get_application_id('Probe status', $templateid)],
                                              'type' => 2, 'value_type' => 3,
                                              'valuemapid' => value_mappings->{'dnstest_probe'}};

    create_item($options);

    $options = { 'description' => '8.2 - Probe {HOST.HOST} has been disabled by tests',
                         'expression' => '{'.$template_name.':dnstest.probe.status[automatic,"{$DNSTEST.IP4.ROOTSERVERS1}","{$DNSTEST.IP6.ROOTSERVERS1}"].last(0)}=0',
                        'priority' => '4',
                };

    create_trigger($options);



    return $templateid;
}

sub create_probe_template {
    my $root_name = shift;

    my $templateid = create_template('Template '.$root_name);

    create_macro('{$DNSTEST.IP4.ENABLED}', '1', $templateid);
    create_macro('{$DNSTEST.IP6.ENABLED}', '1', $templateid);
    create_macro('{$DNSTEST.RESOLVER}', '127.0.0.1', $templateid);
    create_macro('{$DNSTEST.RDDS.ENABLED}', '1', $templateid);
    create_macro('{$DNSTEST.EPP.ENABLED}', '1', $templateid);

    return $templateid;
}

sub update_root_servers {
    my $content = LWP::Simple::get('http://www.internic.net/zones/named.root');

    my $macro_value_v4;
    my $macro_value_v6;
    my $macros_v4;
    my $macros_v6;
    my $cnt_macros_v4 = 1;
    my $cnt_macros_v6 = 1;

    return unless defined $content;

    for my $str (split("\n", $content)) {
	if ($str=~/.+ROOT\-SERVERS.+\sA\s+(.+)$/ and $OPTS{'ipv4'} == 1) {
	    if (defined($macro_value_v4) and length($macro_value_v4.','.$1) > 255) {
                $macros_v4 = $macros_v4.','.'{$DNSTEST.IP4.ROOTSERVERS'.$cnt_macros_v4.'}' if defined($macros_v4);
                $macros_v4 = '{$DNSTEST.IP4.ROOTSERVERS'.$cnt_macros_v4.'}' unless defined $macros_v4;
                create_macro('{$DNSTEST.IP4.ROOTSERVERS'.$cnt_macros_v4.'}', $macro_value_v4);
                undef $macro_value_v4;
                $cnt_macros_v4++;
            }

	    $macro_value_v4 = $macro_value_v4.','.$1 if defined($macro_value_v4);
	    $macro_value_v4 = $1 unless defined $macro_value_v4;
	}

	if ($str=~/.+ROOT\-SERVERS.+AAAA\s+(.+)$/ and $OPTS{'ipv6'} == 1) {
            if (defined($macro_value_v6) and length($macro_value_v6.','.$1) > 255) {
                $macros_v6 = $macros_v6.','.'{$DNSTEST.IP6.ROOTSERVERS'.$cnt_macros_v6.'}' if defined($macros_v6);
                $macros_v6 = '{$DNSTEST.IP6.ROOTSERVERS'.$cnt_macros_v6.'}' unless defined $macros_v6;
                create_macro('{$DNSTEST.IP6.ROOTSERVERS'.$cnt_macros_v6.'}', $macro_value_v6);
                undef $macro_value_v6;
                $cnt_macros_v6++;
            }

            $macro_value_v6 = $macro_value_v6.','.$1 if defined($macro_value_v6);
            $macro_value_v6 = $1 unless defined $macro_value_v6;
        }
    }

    unless (defined($macros_v4)) {
	if (defined($macro_value_v4)) {
	    $macros_v4 = '{$DNSTEST.IP4.ROOTSERVERS1}';
	    create_macro('{$DNSTEST.IP4.ROOTSERVERS1}', $macro_value_v4);
	}
    }
    else {
	$macros_v4 = $macros_v4.','.'{$DNSTEST.IP4.ROOTSERVERS'.$cnt_macros_v4.'}';
	create_macro('{$DNSTEST.IP4.ROOTSERVERS'.$cnt_macros_v4.'}', $macro_value_v4);
    }

    unless (defined($macros_v6)) {
	if (defined($macro_value_v6)) {
            $macros_v6 = '{$DNSTEST.IP6.ROOTSERVERS1}';
            create_macro('{$DNSTEST.IP6.ROOTSERVERS1}', $macro_value_v6);
        }
    }
    else {
        $macros_v6 = $macros_v6.','.'{$DNSTEST.IP6.ROOTSERVERS'.$cnt_macros_v6.'}';
        create_macro('{$DNSTEST.IP6.ROOTSERVERS'.$cnt_macros_v6.'}', $macro_value_v6);
    }

    return '"'.$macros_v4.'","'.$macros_v6.'"' if defined $macros_v4 and defined $macros_v6;
    return '"'.$macros_v4.'"' if defined $macros_v4 and !defined $macros_v6;
    return ','.$macros_v6.'"' if !defined $macros_v4 and defined $macros_v6;

    return ',,';
}

sub create_main_template {
    my $tld = shift;
    my $ns_servers = shift;

    my $template_name = 'Template '.$tld;

    my $templateid = create_template($template_name);

    pfail("cannot create Template '".$template_name."'") unless ($templateid);

    foreach my $ns_name (sort keys %{$ns_servers}) {
	print $ns_name."\n";

        my @ipv4 = defined(@{$ns_servers->{$ns_name}{'v4'}}) ? @{$ns_servers->{$ns_name}{'v4'}} : undef;
	my @ipv6 = defined(@{$ns_servers->{$ns_name}{'v6'}}) ? @{$ns_servers->{$ns_name}{'v6'}} : undef;

        foreach (my $i_ipv4 = 0; $i_ipv4 <= $#ipv4; $i_ipv4++) {
	    next unless defined $ipv4[$i_ipv4];
	    print "	--v4     $ipv4[$i_ipv4]\n";

            create_item_dns_rtt($ns_name, $ipv4[$i_ipv4], $templateid, $template_name, "tcp");
	    create_item_dns_rtt($ns_name, $ipv4[$i_ipv4], $templateid, $template_name, "udp");
    	    create_item_dns_udp_upd($ns_name, $ipv4[$i_ipv4], $templateid) if (defined($OPTS{'epp-server'}));
        }

	foreach (my $i_ipv6 = 0; $i_ipv6 <= $#ipv6; $i_ipv6++) {
	    next unless defined $ipv6[$i_ipv6];
    	    print "	--v6     $ipv6[$i_ipv6]\n";

	    create_item_dns_rtt($ns_name, $ipv6[$i_ipv6], $templateid, $template_name, "tcp");
    	    create_item_dns_rtt($ns_name, $ipv6[$i_ipv6], $templateid, $template_name, "udp");
    	    create_item_dns_udp_upd($ns_name, $ipv6[$i_ipv6], $templateid) if (defined($OPTS{'epp-server'}));
        }
    }

    create_items_dns($templateid, $template_name);
    create_items_rdds($templateid, $template_name) if (defined($OPTS{'rdds43-servers'}));
    create_items_epp($templateid, $template_name) if (defined($OPTS{'epp-server'}));

    create_macro('{$DNSTEST.TLD}', $tld, $templateid);
    create_macro('{$DNSTEST.DNS.TESTPREFIX}', $OPTS{'dns-test-prefix'}, $templateid);
    create_macro('{$DNSTEST.RDDS.TESTPREFIX}', $OPTS{'rdds-test-prefix'}, $templateid) if (defined($OPTS{'rdds-test-prefix'}));
    create_macro('{$DNSTEST.RDDS.NS.STRING}', defined($OPTS{'rdds-ns-string'}) ? $OPTS{'rdds-ns-string'} : $cfg_default_rdds_ns_string, $templateid);
    create_macro('{$DNSTEST.TLD.DNSSEC.ENABLED}', defined($OPTS{'dnssec'}) ? 1 : 0, $templateid);
    create_macro('{$DNSTEST.TLD.RDDS.ENABLED}', defined($OPTS{'rdds43-servers'}) ? 1 : 0, $templateid);
    create_macro('{$DNSTEST.TLD.EPP.ENABLED}', defined($OPTS{'epp-server'}) ? 1 : 0, $templateid);

    return $templateid;
}

sub create_all_slv_ns_items {
    my $ns_name = shift;
    my $ip = shift;
    my $hostid = shift;

    create_slv_item('% of successful monthly DNS resolution RTT (UDP): $1 ($2)', 'dnstest.slv.dns.ns.rtt.udp.month['.$ns_name.','.$ip.']', $hostid, $VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]);
    create_slv_item('% of successful monthly DNS resolution RTT (TCP): $1 ($2)', 'dnstest.slv.dns.ns.rtt.tcp.month['.$ns_name.','.$ip.']', $hostid, $VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]);
    create_slv_item('% of successful monthly DNS update time: $1 ($2)', 'dnstest.slv.dns.ns.upd.month['.$ns_name.','.$ip.']', $hostid, $VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]) if (defined($OPTS{'epp-server'}));
    create_slv_item('DNS NS availability: $1 ($2)', 'dnstest.slv.dns.ns.avail['.$ns_name.','.$ip.']', $hostid, $VALUE_TYPE_AVAIL, [get_application_id(APP_SLV_PARTTEST, $hostid)]);
    create_slv_item('% of monthly DNS NS availability: $1 ($2)', 'dnstest.slv.dns.ns.month['.$ns_name.','.$ip.']', $hostid, $VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]);
}

sub create_slv_ns_items {
    my $ns_servers = shift;
    my $hostid = shift;

    foreach my $ns_name (sort keys %{$ns_servers}) {
        my @ipv4 = defined(@{$ns_servers->{$ns_name}{'v4'}}) ? @{$ns_servers->{$ns_name}{'v4'}} : undef;
	my @ipv6 = defined(@{$ns_servers->{$ns_name}{'v6'}}) ? @{$ns_servers->{$ns_name}{'v6'}} : undef;

        foreach (my $i_ipv4 = 0; $i_ipv4 <= $#ipv4; $i_ipv4++) {
	    next unless defined $ipv4[$i_ipv4];

	    create_all_slv_ns_items($ns_name, $ipv4[$i_ipv4], $hostid);
        }

	foreach (my $i_ipv6 = 0; $i_ipv6 <= $#ipv6; $i_ipv6++) {
	    next unless defined $ipv6[$i_ipv6];

	    create_all_slv_ns_items($ns_name, $ipv6[$i_ipv6], $hostid);
        }
    }
}

sub create_slv_items {
    my $ns_servers = shift;
    my $hostid = shift;
    my $host_name = shift;

    create_slv_ns_items($ns_servers, $hostid);

    create_slv_item('DNS availability', 'dnstest.slv.dns.avail', $hostid, $VALUE_TYPE_AVAIL, [get_application_id(APP_SLV_PARTTEST, $hostid)]);

    my $options;

    # NB! Configuration trigger that is used in PHP and C code to detect incident!
    # priority must be set to 0!
    $options = { 'description' => '5.2.4 DNS service is not available at {HOST.HOST} TLD',
                         'expression' => '({TRIGGER.VALUE}=0&'.
						'{'.$host_name.':dnstest.slv.dns.avail.count(#{$INCIDENT.DNS.FAIL},0,"eq")}={$INCIDENT.DNS.FAIL})|'.
					 '({TRIGGER.VALUE}=1&'.
						'{'.$host_name.':dnstest.slv.dns.avail.count(#{$INCIDENT.DNS.RECOVER},0,"ne")}<{$INCIDENT.DNS.RECOVER})',
                        'priority' => '0',
                };

    create_trigger($options);

    create_slv_item('DNS weekly unavailability', 'dnstest.slv.dns.rollweek', $hostid, $VALUE_TYPE_PERC, [get_application_id(APP_SLV_ROLLWEEK, $hostid)]);

    $options = { 'description' => '12.2 DNS Service Availability [{ITEM.LASTVALUE1}] > 10%',
                         'expression' => '({'.$host_name.':dnstest.slv.dns.rollweek.last(0)}=10|'.
					'{'.$host_name.':dnstest.slv.dns.rollweek.last(0)}>10)&'.
					'{'.$host_name.':dnstest.slv.dns.rollweek.last(0)}<25',
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => '12.2 DNS Service Availability [{ITEM.LASTVALUE1}] > 25%',
                         'expression' => '({'.$host_name.':dnstest.slv.dns.rollweek.last(0)}=25|'.
					'{'.$host_name.':dnstest.slv.dns.rollweek.last(0)}>25)&'.
                                        '{'.$host_name.':dnstest.slv.dns.rollweek.last(0)}<50',
                        'priority' => '3',
                };

    create_trigger($options);

    $options = { 'description' => '12.2 DNS Service Availability [{ITEM.LASTVALUE1}] > 50%',
                         'expression' => '({'.$host_name.':dnstest.slv.dns.rollweek.last(0)}=50|'.
					'{'.$host_name.':dnstest.slv.dns.rollweek.last(0)}>50)&'.
                                        '{'.$host_name.':dnstest.slv.dns.rollweek.last(0)}<75',
                        'priority' => '3',
                };

    create_trigger($options);

    $options = { 'description' => '12.2 DNS Service Availability [{ITEM.LASTVALUE1}] > 75%',
                         'expression' => '({'.$host_name.':dnstest.slv.dns.rollweek.last(0)}>75|'.
					'{'.$host_name.':dnstest.slv.dns.rollweek.last(0)}>75)&'.
                                        '{'.$host_name.':dnstest.slv.dns.rollweek.last(0)}<90',
                        'priority' => '4',
                };

    create_trigger($options);

    $options = { 'description' => '12.2 DNS Service Availability [{ITEM.LASTVALUE1}] > 90%',
                         'expression' => '({'.$host_name.':dnstest.slv.dns.rollweek.last(0)}=90|'.
					'{'.$host_name.':dnstest.slv.dns.rollweek.last(0)}>90)&'.
                                        '{'.$host_name.':dnstest.slv.dns.rollweek.last(0)}<100',
                        'priority' => '4',
                };

    create_trigger($options);

    $options = { 'description' => '12.2 DNS Service Availability [{ITEM.LASTVALUE1}] > 100%',
                         'expression' => '{'.$host_name.':dnstest.slv.dns.rollweek.last(0)}=100|'.
					'{'.$host_name.':dnstest.slv.dns.rollweek.last(0)}>100',
                        'priority' => '5',
                };

    create_trigger($options);

    if (defined($OPTS{'dnssec'})) {
	create_slv_item('DNSSEC availability', 'dnstest.slv.dnssec.avail', $hostid, $VALUE_TYPE_AVAIL, [get_application_id(APP_SLV_PARTTEST, $hostid)]);

	# NB! Configuration trigger that is used in PHP and C code to detect incident!
	# priority must be set to 0!
	$options = { 'description' => '5.3.3 DNSSEC service is not available at {HOST.HOST} TLD',
		     'expression' => '({TRIGGER.VALUE}=0&'.
			 '{'.$host_name.':dnstest.slv.dnssec.avail.count(#{$INCIDENT.DNSSEC.FAIL},0,"eq")}={$INCIDENT.DNSSEC.FAIL})|'.
			 '({TRIGGER.VALUE}=1&'.
			 '{'.$host_name.':dnstest.slv.dnssec.avail.count(#{$INCIDENT.DNSSEC.RECOVER},0,"ne")}<{$INCIDENT.DNSSEC.RECOVER})',
			 'priority' => '0',
	};

	create_trigger($options);

	create_slv_item('DNSSEC weekly unavailability', 'dnstest.slv.dnssec.rollweek', $hostid, $VALUE_TYPE_PERC, [get_application_id(APP_SLV_ROLLWEEK, $hostid)]);

	$options = { 'description' => '12.2 DNSSEC proper resolution > 10%',
		     'expression' => '{'.$host_name.':dnstest.slv.dnssec.rollweek.last(0)}>10&'.
			 '{'.$host_name.':dnstest.slv.dnssec.rollweek.last(0)}<25',
			 'priority' => '2',
	};

	create_trigger($options);

	$options = { 'description' => '12.2 DNSSEC proper resolution > 25%',
		     'expression' => '{'.$host_name.':dnstest.slv.dnssec.rollweek.last(0)}>25&'.
			 '{'.$host_name.':dnstest.slv.dnssec.rollweek.last(0)}<50',
			 'priority' => '3',
	};

	create_trigger($options);

	$options = { 'description' => '12.2 DNSSEC proper resolution > 50%',
		     'expression' => '{'.$host_name.':dnstest.slv.dnssec.rollweek.last(0)}>50&'.
			 '{'.$host_name.':dnstest.slv.dnssec.rollweek.last(0)}<75',
			 'priority' => '3',
	};

	create_trigger($options);

	$options = { 'description' => '12.2 DNSSEC proper resolution > 75%',
		     'expression' => '{'.$host_name.':dnstest.slv.dnssec.rollweek.last(0)}>75&'.
			 '{'.$host_name.':dnstest.slv.dnssec.rollweek.last(0)}<90',
			 'priority' => '4',
	};

	create_trigger($options);

	$options = { 'description' => '12.2 DNSSEC proper resolution > 90%',
		     'expression' => '{'.$host_name.':dnstest.slv.dnssec.rollweek.last(0)}>90&'.
			 '{'.$host_name.':dnstest.slv.dnssec.rollweek.last(0)}<100',
			 'priority' => '4',
	};

	create_trigger($options);

	$options = { 'description' => '12.2 DNSSEC proper resolution > 100%',
		     'expression' => '{'.$host_name.':dnstest.slv.dnssec.rollweek.last(0)}>100',
		     'priority' => '5',
	};

	create_trigger($options);
    }

    if (defined($OPTS{'rdds43-servers'})) {
	create_slv_item('RDDS availability', 'dnstest.slv.rdds.avail', $hostid, $VALUE_TYPE_AVAIL, [get_application_id(APP_SLV_PARTTEST, $hostid)]);

	# NB! Configuration trigger that is used in PHP and C code to detect incident!
	# priority must be set to 0!
	$options = { 'description' => '6.2.3 RDDS service is not available at {HOST.HOST} TLD',
		     'expression' => '({TRIGGER.VALUE}=0&'.
			 '{'.$host_name.':dnstest.slv.rdds.avail.count(#{$INCIDENT.RDDS.FAIL},0,"eq")}={$INCIDENT.RDDS.FAIL})|'.
			 '({TRIGGER.VALUE}=1&'.
			 '{'.$host_name.':dnstest.slv.rdds.avail.count(#{$INCIDENT.RDDS.RECOVER},0,"ne")}<{$INCIDENT.RDDS.RECOVER})',
			 'priority' => '0',
	};

	create_trigger($options);

	create_slv_item('RDDS weekly unavailability', 'dnstest.slv.rdds.rollweek', $hostid, $VALUE_TYPE_PERC, [get_application_id(APP_SLV_ROLLWEEK, $hostid)]);

        $options = { 'description' => '12.2 RDDS Availability > 10%',
		     'expression' => '{'.$host_name.':dnstest.slv.rdds.rollweek.last(0)}>10&'.
			 '{'.$host_name.':dnstest.slv.rdds.rollweek.last(0)}<25',
			 'priority' => '2',
	};

	create_trigger($options);

	$options = { 'description' => '12.2 RDDS Availability > 25%',
		     'expression' => '{'.$host_name.':dnstest.slv.rdds.rollweek.last(0)}>25&'.
			 '{'.$host_name.':dnstest.slv.rdds.rollweek.last(0)}<50',
			 'priority' => '3',
	};

	create_trigger($options);

	$options = { 'description' => '12.2 RDDS Availability > 50%',
		     'expression' => '{'.$host_name.':dnstest.slv.rdds.rollweek.last(0)}>50&'.
			 '{'.$host_name.':dnstest.slv.rdds.rollweek.last(0)}<75',
			 'priority' => '3',
	};

	create_trigger($options);

	$options = { 'description' => '12.2 RDDS Availability > 75%',
		     'expression' => '{'.$host_name.':dnstest.slv.rdds.rollweek.last(0)}>75&'.
			 '{'.$host_name.':dnstest.slv.rdds.rollweek.last(0)}<90',
			 'priority' => '4',
	};

	create_trigger($options);

	$options = { 'description' => '12.2 RDDS Availability > 90%',
		     'expression' => '{'.$host_name.':dnstest.slv.rdds.rollweek.last(0)}>90&'.
			 '{'.$host_name.':dnstest.slv.rdds.rollweek.last(0)}<100',
			 'priority' => '4',
	};

	create_trigger($options);

	$options = { 'description' => '12.2 RDDS Availability > 100%',
		     'expression' => '{'.$host_name.':dnstest.slv.rdds.rollweek.last(0)}>100',
		     'priority' => '5',
	};

	create_trigger($options);

	create_slv_item('% of successful monthly RDDS43 resolution RTT', 'dnstest.slv.rdds.43.rtt.month', $hostid, $VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]);
	create_slv_item('% of successful monthly RDDS80 resolution RTT', 'dnstest.slv.rdds.80.rtt.month', $hostid, $VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]);
	create_slv_item('% of successful monthly RDDS update time', 'dnstest.slv.rdds.upd.month', $hostid, $VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]) if (defined($OPTS{'epp-server'}));
    }

    if (defined($OPTS{'epp-server'})) {
	create_slv_item('EPP availability', 'dnstest.slv.epp.avail', $hostid, $VALUE_TYPE_AVAIL, [get_application_id(APP_SLV_PARTTEST, $hostid)]);
	create_slv_item('EPP weekly unavailability', 'dnstest.slv.epp.rollweek', $hostid, $VALUE_TYPE_PERC, [get_application_id(APP_SLV_ROLLWEEK, $hostid)]);

	create_slv_item('% of successful monthly EPP LOGIN resolution RTT', 'dnstest.slv.epp.rtt.login.month', $hostid, $VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]);
	create_slv_item('% of successful monthly EPP UPDATE resolution RTT', 'dnstest.slv.epp.rtt.update.month', $hostid, $VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]);
	create_slv_item('% of successful monthly EPP INFO resolution RTT', 'dnstest.slv.epp.rtt.info.month', $hostid, $VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]);

	# NB! Configuration trigger that is used in PHP and C code to detect incident!
	# priority must be set to 0!
	$options = { 'description' => '7.2.3 EPP service is not available at {HOST.HOST} TLD',
		     'expression' => '({TRIGGER.VALUE}=0&'.
			 '{'.$host_name.':dnstest.slv.epp.avail.count(#{$INCIDENT.EPP.FAIL},0,"eq")}={$INCIDENT.EPP.FAIL})|'.
			 '({TRIGGER.VALUE}=1&'.
			 '{'.$host_name.':dnstest.slv.epp.avail.count(#{$INCIDENT.EPP.RECOVER},0,"ne")}<{$INCIDENT.EPP.RECOVER})',
			 'priority' => '0',
	};

	create_trigger($options);

        $options = { 'description' => '12.2 EPP Service Availability [{ITEM.LASTVALUE1}] > 10%',
                         'expression' => '({'.$host_name.':dnstest.slv.epp.rollweek.last(0)}=10|'.
                                        '{'.$host_name.':dnstest.slv.epp.rollweek.last(0)}>10)&'.
                                        '{'.$host_name.':dnstest.slv.epp.rollweek.last(0)}<25',
                        'priority' => '2',
                };

	create_trigger($options);

	$options = { 'description' => '12.2 EPP Service Availability [{ITEM.LASTVALUE1}] > 25%',
                         'expression' => '({'.$host_name.':dnstest.slv.epp.rollweek.last(0)}=25|'.
                                        '{'.$host_name.':dnstest.slv.epp.rollweek.last(0)}>25)&'.
                                        '{'.$host_name.':dnstest.slv.epp.rollweek.last(0)}<50',
                        'priority' => '3',
                };

	create_trigger($options);

	$options = { 'description' => '12.2 EPP Service Availability [{ITEM.LASTVALUE1}] > 50%',
                         'expression' => '({'.$host_name.':dnstest.slv.epp.rollweek.last(0)}=50|'.
                                        '{'.$host_name.':dnstest.slv.epp.rollweek.last(0)}>50)&'.
                                        '{'.$host_name.':dnstest.slv.epp.rollweek.last(0)}<75',
                        'priority' => '3',
                };

	create_trigger($options);

	$options = { 'description' => '12.2 EPP Service Availability [{ITEM.LASTVALUE1}] > 75%',
                         'expression' => '({'.$host_name.':dnstest.slv.epp.rollweek.last(0)}>75|'.
                                        '{'.$host_name.':dnstest.slv.epp.rollweek.last(0)}>75)&'.
                                        '{'.$host_name.':dnstest.slv.epp.rollweek.last(0)}<90',
                        'priority' => '4',
                };

	create_trigger($options);

	$options = { 'description' => '12.2 EPP Service Availability [{ITEM.LASTVALUE1}] > 90%',
                         'expression' => '({'.$host_name.':dnstest.slv.epp.rollweek.last(0)}=90|'.
                                        '{'.$host_name.':dnstest.slv.epp.rollweek.last(0)}>90)&'.
                                        '{'.$host_name.':dnstest.slv.epp.rollweek.last(0)}<100',
                        'priority' => '4',
                };

	create_trigger($options);

        $options = { 'description' => '12.2 EPP Service Availability [{ITEM.LASTVALUE1}] > 100%',
                         'expression' => '{'.$host_name.':dnstest.slv.epp.rollweek.last(0)}=100|'.
                                        '{'.$host_name.':dnstest.slv.epp.rollweek.last(0)}>100',
                        'priority' => '5',
                };

	create_trigger($options);

    }
}

# calculated items, configuration history (TODO: rename host to something like config_history)
sub create_dnstest_items {
    my $hostid = shift;

    my $options;
    my $appid = get_application_id('Configuration', $hostid);

    my $delay = 60; # every minute
    foreach my $m (
	'INCIDENT.DNS.FAIL',
	'INCIDENT.DNS.RECOVER',
	'INCIDENT.DNSSEC.FAIL',
	'INCIDENT.DNSSEC.RECOVER',
	'INCIDENT.RDDS.FAIL',
	'INCIDENT.RDDS.RECOVER',
	'INCIDENT.EPP.FAIL',
	'INCIDENT.EPP.RECOVER',
	'DNSTEST.DNS.UDP.DELAY',
	'DNSTEST.RDDS.DELAY',
	'DNSTEST.EPP.DELAY',
	'DNSTEST.DNS.UDP.RTT',
	'DNSTEST.DNS.AVAIL.MINNS')
    {
	$options = {'name' => '$1 value',
		   'key_'=> 'dnstest.configvalue['.$m.']',
		   'hostid' => $hostid,
		   'applications' => [$appid],
		   'params' => '{$'.$m.'}',
		   'delay' => $delay,
		   'type' => 15, 'value_type' => 3};

	create_item($options);
    }

    $delay = 60 * 60 * 24; # every day
    foreach my $m (
	'DNSTEST.SLV.DNS.UDP.RTT',
	'DNSTEST.SLV.DNS.TCP.RTT',
	'DNSTEST.SLV.NS.AVAIL',
	'DNSTEST.SLV.RDDS43.RTT',
	'DNSTEST.SLV.RDDS80.RTT',
	'DNSTEST.SLV.RDDS.UPD',
	'DNSTEST.SLV.DNS.NS.UPD',
	'DNSTEST.SLV.EPP.LOGIN',
	'DNSTEST.SLV.EPP.UPDATE',
	'DNSTEST.SLV.EPP.INFO')
    {
	$options = {'name' => '$1 value',
		   'key_'=> 'dnstest.configvalue['.$m.']',
		   'hostid' => $hostid,
		   'applications' => [$appid],
		   'params' => '{$'.$m.'}',
		   'delay' => $delay,
		   'type' => 15, 'value_type' => 3};

	create_item($options);
    }
}

sub pfail {
    print("Error: ", @_, "\n");
    exit -1;
}

sub create_cron_items {
    my $slv_path = $config->{'slv'}->{'path'};

    my $rv = opendir DIR, $slv_path;

    pfail("cannot open $slv_path") unless ($rv);

    my $slv_file;
    while (($slv_file = readdir DIR)) {
	if ($slv_file =~ /\.slv\..*\.month\.pl$/) {
	    # check monthly data once a day
	    system("echo '0 0 * * * root $slv_path/$slv_file' > /etc/cron.d/$slv_file");
	} else {
	    system("echo '* * * * * root $slv_path/$slv_file' > /etc/cron.d/$slv_file");
	}
    }
}

sub usage {
    my ($opt_name, $opt_value) = @_;

    print <<EOF;

    Usage: $0 [options]

Required options

        --tld=STRING
                TLD name
        --dns-test-prefix=STRING
                domain test prefix for DNS monitoring (specify *RANDOMTLD* for root servers monitoring)

Other options

        --ipv4
                enable IPv4
		(default: disabled)
        --ipv6
                enable IPv6
		(default: disabled)
        --dnssec
                enable DNSSEC in DNS tests
		(default: disabled)
        --ns-servers-v4=STRING
                list of IPv4 name servers separated by space (name and IP separated by comma): "NAME,IP[ NAME,IP2 ...]"
		(default: get the list from local resolver)
        --ns-servers-v6=STRING
                list of IPv6 name servers separated by space (name and IP separated by comma): "NAME,IP[ NAME,IP2 ...]"
		(default: get the list from local resolver)
        --rdds43-servers=STRING
                list of RDDS43 servers separated by comma: "NAME1,NAME2,..."
        --rdds80-servers=STRING
                list of RDDS80 servers separated by comma: "NAME1,NAME2,..."
        --epp-server=STRING
                specify EPP server
        --rdds-ns-string=STRING
                name server prefix in the WHOIS output
		(default: "$cfg_default_rdds_ns_string")
        --rdds-test-prefix=STRING
		domain test prefix for RDDS monitoring (needed only if rdds servers specified)
        --only-cron
		only create cron jobs and exit
        --help
                display this message
EOF
exit(1);
}

sub validate_input {
    my $msg = "";

    $msg  = "TLD must be specified (--tld)\n" unless (defined($OPTS{'tld'}));
    $msg .= "at least one IPv4 or IPv6 must be enabled (--ipv4 or --ipv6)\n" unless ($OPTS{'ipv4'} or $OPTS{'ipv6'});
    $msg .= "DNS test prefix must be specified (--dns-test-prefix)\n" unless (defined($OPTS{'dns-test-prefix'}));
    $msg .= "RDDS test prefix must be specified (--rdds-test-prefix)\n" if ((defined($OPTS{'rdds43-servers'}) and !defined($OPTS{'rdds-test-prefix'})) or (defined($OPTS{'rdds80-servers'}) and !defined($OPTS{'rdds-test-prefix'})));
    $msg .= "none or both --rdds43-servers and --rdds80-servers should be specified\n" if ((defined($OPTS{'rdds43-servers'}) and !defined($OPTS{'rdds80-servers'})) or
											 (defined($OPTS{'rdds80-servers'}) and !defined($OPTS{'rdds43-servers'})));

    if ($msg ne "")
    {
	print($msg);
	usage();
    }
}

sub lc_options {
    $OPTS{$_} = lc($OPTS{$_}) foreach (keys(%OPTS));
}

sub create_probe_status_host {
    my $groupid = shift;

    my $name = 'Probes Status';

    my $hostid = create_host({'groups' => [{'groupid' => $groupid}],
                                          'host' => $name,
                                          'interfaces' => [{'type' => 1, 'main' => 1, 'useip' => 1,
                                                            'ip'=> '127.0.0.1',
                                                            'dns' => '', 'port' => '10050'}]
                });

    my $interfaceid = $zabbix->get('hostinterface', {'hostids' => $hostid, 'output' => ['interfaceid']});

    my $options = {'name' => 'Total number of probes',
                                              'key_'=> 'online.nodes.pl[total]',
                                              'hostid' => $hostid,
					      'interfaceid' => $interfaceid->{'interfaceid'},
                                              'applications' => [get_application_id('Probes availability', $hostid)],
                                              'type' => 10, 'value_type' => 3,
					      'delay' => 300,
                                              };
    create_item($options);

    $options = {'name' => 'Number of online probes',
                                              'key_'=> 'online.nodes.pl',
                                              'hostid' => $hostid,
					      'interfaceid' => $interfaceid->{'interfaceid'},
                                              'applications' => [get_application_id('Probes availability', $hostid)],
                                              'type' => 10, 'value_type' => 3,
					      'delay' => 60,
                                              };
    create_item($options);

    $options = { 'description' => '12.2 Online probes for DNS test [{ITEM.LASTVALUE1}] is less than [{$DNSTEST.DNS.PROBE.ONLINE}]',
                         'expression' => '{'.$name.':online.nodes.pl.last(0)}<{$DNSTEST.DNS.PROBE.ONLINE}',
                        'priority' => '5',
                };

    create_trigger($options);

    $options = { 'description' => '12.2 Online probes for RDDS test [{ITEM.LASTVALUE1}] is less than [{$DNSTEST.RDDS.PROBE.ONLINE}]',
                         'expression' => '{'.$name.':online.nodes.pl.last(0)}<{$DNSTEST.RDDS.PROBE.ONLINE}',
                        'priority' => '5',
                };

    create_trigger($options);

    $options = { 'description' => '12.2 Online probes for EPP test [{ITEM.LASTVALUE1}] is less than [{$DNSTEST.EPP.PROBE.ONLINE}]',
                         'expression' => '{'.$name.':online.nodes.pl.last(0)}<{$DNSTEST.EPP.PROBE.ONLINE}',
                        'priority' => '5',
                };

    create_trigger($options);
}

sub add_default_actions() {

}
