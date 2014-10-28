#!/usr/bin/perl
#
# - DNS availability test		(data collection)	rsm.dns.udp			(simple, every minute)
#								rsm.dns.tcp			(simple, every 50 minutes)
#								rsm.dns.udp.rtt			(used for trapper)
#								rsm.dns.tcp.rtt			-|-
#								rsm.dns.udp.upd			-|-
# - RDDS availability test		(data collection)	rsm.rdds			(simple, every minutes)
#   (also RDDS43 and RDDS80					rsm.rdds.43.ip			(used for trapper)
#   availability at a particular				rsm.rdds.43.rtt			-|-
#   minute)							rsm.rdds.43.upd			-|-
#								rsm.rdds.80.ip			-|-
#								rsm.rdds.80.rtt			-|-
# - EPP					(data collection)
# - login RTT							rsm.epp.rtt[{$RSM.TLD},login]	(simple, every 5 minutes)
# - update RTT							rsm.epp.rtt[{$RSM.TLD},update]	-|-
# - info RTT							rsm.epp.rtt[{$RSM.TLD},info]	-|-
#
# - DNS NS availability			(given minute)		rsm.slv.dns.ns.avail		(used for trapper)
# - DNS NS monthly availability		(monthly)		rsm.slv.dns.ns.month		-|-	+
# - DNS monthly resolution RTT		(monthly)		rsm.slv.dns.ns.rtt.udp.month	-|-	+
# - DNS monthly resolution RTT (TCP)	(monthly, TCP)		rsm.slv.dns.ns.rtt.tcp.month	-|-	+
# - DNS monthly update time		(monthly)		rsm.slv.dns.ns.upd.month	-|-	+
# - DNS availability			(given minute)		rsm.slv.dns.avail		-|-	+
# - DNS rolling week			(rolling week)		rsm.slv.dns.rollweek		-|-	+
# - DNSSEC proper resolution		(given minute)		rsm.slv.dnssec.avail		-|-	+
# - DNSSEC rolling week			(rolling week)		rsm.slv.dnssec.rollweek		-|-	+
#
# - RDDS availability			(given minute)		rsm.slv.rdds.avail		-|-	-
# - RDDS rolling week			(rolling week)		rsm.slv.rdds.rollweek		-|-	-
# - RDDS43 monthly resolution RTT	(monthly)		rsm.slv.rdds.43.rtt.month	-|-	-
# - RDDS80 monthly resolution RTT	(monthly)		rsm.slv.rdds.80.rtt.month	-|-	-
# - RDDS monthly update time		(monthly)		rsm.slv.rdds.upd.month		-|-	-
#

use lib '/opt/zabbix/scripts';

use strict;
use warnings;
use Zabbix;
use Getopt::Long;
use MIME::Base64;
use Digest::MD5 qw(md5_hex);
use Expect;
use Data::Dumper;
use RSM;
use TLD_constants qw(:general :templates :value_types :ec :rsm :slv :config :api);
use TLDs;

sub create_global_macros;
sub create_tld_host($$$$);
sub create_probe_health_tmpl;
sub manage_tld_objects($$$$$);
sub manage_tld_hosts($$);

sub check_api_error($);

my $trigger_rollweek_thresholds = rsm_trigger_rollweek_thresholds;

my $cfg_global_macros = cfg_global_macros;

my ($rsm_groupid, $rsm_hostid);

my ($ns_servers, $root_servers_macros);

my ($main_templateid, $tld_groupid, $tld_type_groupid, $tlds_groupid, $tld_hostid, $probes_groupid, $probes_mon_groupid, $proxy_mon_templateid);

my %OPTS;
my $rv = GetOptions(\%OPTS,
		    "tld=s",
		    "type=s",
		    "delete!",
		    "disable!",
		    "rdds43-servers=s",
		    "rdds80-servers=s",
		    "dns-test-prefix=s",
		    "rdds-test-prefix=s",
		    "ipv4!",
		    "ipv6!",
		    "dns!",
		    "epp!",
		    "rdds!",
		    "dnssec!",
		    "epp-servers=s",
		    "epp-user=s",
		    "epp-cert=s",
		    "epp-privkey=s",
		    "epp-commands=s",
		    "epp-serverid=s",
		    "epp-test-prefix=s",
		    "epp-servercert=s",
		    "ns-servers-v4=s",
		    "ns-servers-v6=s",
		    "rdds-ns-string=s",
		    "only-cron!",
		    "verbose!",
		    "quiet!",
		    "help|?");

usage() if ($OPTS{'help'} or not $rv);

validate_input();
lc_options();

# Expect stuff for EPP
my $exp_timeout = 3;
my $exp_command = '/opt/zabbix/bin/rsm_epp_enc';
my $exp_output;

my $config = get_rsm_config();

pfail("SLV scripts path is not specified. Please check configuration file") unless defined $config->{'slv'}->{'path'};

#### Creating cron objects ####
if (defined($OPTS{'only-cron'})) {
    create_cron_items($config->{'slv'}->{'path'});
    exit;
}

pfail("Zabbix API URL is not specified. Please check configuration file") unless defined $config->{'zapi'}->{'url'};
pfail("Username for Zabbix API is not specified. Please check configuration file") unless defined $config->{'zapi'}->{'user'};
pfail("Password for Zabbix API is not specified. Please check configuration file") unless defined $config->{'zapi'}->{'password'};

my $result = zbx_connect($config->{'zapi'}->{'url'}, $config->{'zapi'}->{'user'}, $config->{'zapi'}->{'password'});

if ($result ne true) {
    pfail("Could not connect to Zabbix API. ".$result->{'data'});
}

#### Deleting TLD or TLD objects ####
if (defined($OPTS{'delete'})) {
    manage_tld_objects('delete', $OPTS{'tld'}, $OPTS{'dns'}, $OPTS{'epp'}, $OPTS{'rdds'});
    exit;
}

#### Disabling TLD or TLD objects ####
if (defined($OPTS{'disable'})) {
    manage_tld_objects('disable',$OPTS{'tld'}, $OPTS{'dns'}, $OPTS{'epp'}, $OPTS{'rdds'});
    exit;
}

#### Adding new TLD ####
my $proxies = get_proxies_list();

pfail("Cannot find existing proxies") if (scalar(keys %{$proxies}) == 0);

## Creating all global macros##
## Please check the function to change default values of macros ##
create_global_macros();

## Geting some global macros related to item refresh interval ##
## Values are used as item update interval ##
foreach my $macro (keys %{$cfg_global_macros}) {
    $cfg_global_macros->{$macro} = get_global_macro_value($macro);
    pfail('cannot get global macro ', $macro) unless defined($cfg_global_macros->{$macro});
}


# RSM host is required to have history of global configuration changes #
# There are monitored changes of global macros #

$rsm_groupid = create_group(rsm_group);

if (defined($rsm_groupid)) {
    $rsm_hostid = create_host({'groups' => [{'groupid' => $rsm_groupid}],
			      'host' => rsm_host,
			      'interfaces' => [{'type' => INTERFACE_TYPE_AGENT, 'main' => true, 'useip' => true, 'ip'=> '127.0.0.1', 'dns' => '', 'port' => '10050'}]});

    if (defined($rsm_hostid)) {
        # calculated items, configuration history (TODO: rename host to something like config_history)
	create_rsm_items($rsm_hostid);
    }
    else {
	print "Could not create/update '".rsm_host."' host. Items are not created/updated.\n";
    }
}
else {
    print "Could not create/update '".rsm_group."' host group. RSM host is not created/updated.\n";
}


$ns_servers = get_ns_servers($OPTS{'tld'});

pfail("Could not retrive NS servers for '".$OPTS{'tld'}."' TLD") unless (scalar(keys %{$ns_servers}));

$root_servers_macros = update_root_servers();

unless (defined($root_servers_macros)) {
    print "Could not retrive list of root servers or create global macros\n";
}

$main_templateid = create_main_template($OPTS{'tld'}, $ns_servers);

pfail("Main templateid is not defined") unless defined $main_templateid;

$tld_groupid = create_group('TLD '.$OPTS{'tld'});

pfail $tld_groupid->{'data'} if check_api_error($tld_groupid) eq true;

$tlds_groupid = create_group('TLDs');

pfail $tlds_groupid->{'data'} if check_api_error($tlds_groupid) eq true;

$tld_type_groupid = create_group($OPTS{'type'});

pfail $tld_type_groupid->{'data'} if check_api_error($tld_type_groupid) eq true;

$tld_hostid = create_tld_host($OPTS{'tld'}, $tld_groupid, $tlds_groupid, $tld_type_groupid);

$probes_groupid = create_group('Probes');

pfail $probes_groupid->{'data'} if check_api_error($probes_groupid) eq true;

$probes_mon_groupid = create_group('Probes - Mon');

pfail $probes_mon_groupid->{'data'} if check_api_error($probes_mon_groupid) eq true;

$proxy_mon_templateid = create_probe_health_tmpl();

## Creating TLD hosts for each probe ##

foreach my $proxyid (sort keys %{$proxies}) {
    my $probe_name = $proxies->{$proxyid}->{'host'};

    print $proxyid."\n";
    print $proxies->{$proxyid}->{'host'}."\n";

    my $proxy_groupid = create_group($probe_name);

    my $probe_templateid = create_probe_template($probe_name);
    my $probe_status_templateid = create_probe_status_template($probe_name, $probe_templateid, $root_servers_macros);

    create_host({'groups' => [{'groupid' => $proxy_groupid}, {'groupid' => $probes_groupid}],
                                          'templates' => [{'templateid' => $probe_status_templateid}],
                                          'host' => $probe_name,
                                          'proxy_hostid' => $proxyid,
                                          'interfaces' => [{'type' => 1, 'main' => true, 'useip' => true,
							    'ip'=> '127.0.0.1',
							    'dns' => '', 'port' => '10050'}]
		});

    my $hostid = create_host({'groups' => [{'groupid' => $probes_mon_groupid}],
                                          'templates' => [{'templateid' => $proxy_mon_templateid}],
                                          'host' => $probe_name.' - mon',
                                          'interfaces' => [{'type' => 1, 'main' => true, 'useip' => true,
                                                            'ip'=> $proxies->{$proxyid}->{'interfaces'}[0]->{'ip'},
                                                            'dns' => '', 'port' => '10050'}]
            		    });

    create_macro('{$RSM.PROXY_NAME}', $probe_name, $hostid, 1);

    create_host({'groups' => [{'groupid' => $tld_groupid}, {'groupid' => $proxy_groupid}],
                                          'templates' => [{'templateid' => $main_templateid}, {'templateid' => $probe_templateid}],
                                          'host' => $OPTS{'tld'}.' '.$probe_name,
                                          'proxy_hostid' => $proxyid,
                                          'interfaces' => [{'type' => 1, 'main' => true, 'useip' => true, 'ip'=> '127.0.0.1', 'dns' => '', 'port' => '10050'}]});
}

create_probe_status_host($probes_mon_groupid);

#### Creating cron objects ####
create_cron_items($config->{'slv'}->{'path'});

exit;

########### FUNCTIONS ###############

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

sub create_item_dns_rtt {
    my $ns_name = shift;
    my $ip = shift;
    my $templateid = shift;
    my $template_name = shift;
    my $proto = shift;
    my $ipv = shift;

    pfail("undefined template ID passed to create_item_dns_rtt()") unless ($templateid);
    pfail("no protocol parameter specified to create_item_dns_rtt()") unless ($proto);

    my $proto_lc = lc($proto);
    my $proto_uc = uc($proto);

    my $item_key = 'rsm.dns.'.$proto_lc.'.rtt[{$RSM.TLD},'.$ns_name.','.$ip.']';

    my $options = {'name' => 'DNS RTT of $2 ($3) ('.$proto_uc.')',
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [get_application_id('DNS RTT ('.$proto_uc.')', $templateid)],
                                              'type' => 2, 'value_type' => 0,
                                              'valuemapid' => rsm_value_mappings->{'rsm_dns'}};

    create_item($options);

    $options = { 'description' => 'DNS-RTT-'.$proto_uc.' {HOST.NAME}: Internal error '.$ns_name.' ['.$ip.']',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_INTERNAL.
                                            '&{'.$template_name.':'.'probe.configvalue[RSM.IP'.$ipv.'.ENABLED]'.'.last(0)}=1',
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => 'DNS-RTT-'.$proto_uc.' {HOST.NAME}: 5.1.1 Step 5 - No reply from Name Server '.$ns_name.' ['.$ip.']',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_DNS_NS_NOREPLY.
					    '&{'.$template_name.':'.'probe.configvalue[RSM.IP'.$ipv.'.ENABLED]'.'.last(0)}=1',
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => 'DNS-RTT-'.$proto_uc.' {HOST.NAME}: 5.1.1 Step 5 - Invalid reply from Name Server '.$ns_name.' ['.$ip.']',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_DNS_NS_ERRREPLY.
                                            '&{'.$template_name.':'.'probe.configvalue[RSM.IP'.$ipv.'.ENABLED]'.'.last(0)}=1',
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => 'DNS-RTT-'.$proto_uc.' {HOST.NAME}: 5.1.1 Step 6 - UNIX timestamp is missing from '.$ns_name.' ['.$ip.']',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_DNS_NS_NOTS.
                                            '&{'.$template_name.':'.'probe.configvalue[RSM.IP'.$ipv.'.ENABLED]'.'.last(0)}=1',
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => 'DNS-RTT-'.$proto_uc.' {HOST.NAME}: 5.1.1 Step 6 - Invalid UNIX timestamp from '.$ns_name.' ['.$ip.']',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_DNS_NS_ERRTS.
                                            '&{'.$template_name.':'.'probe.configvalue[RSM.IP'.$ipv.'.ENABLED]'.'.last(0)}=1',
                        'priority' => '2',
                };

    create_trigger($options);

    if (defined($OPTS{'dnssec'})) {
	$options = { 'description' => 'DNS-RTT-'.$proto_uc.' {HOST.NAME}: 5.1.1 Step 7 - DNSSEC error from '.$ns_name.' ['.$ip.']',
		     'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_DNS_NS_ERRSIG.
                                            '&{'.$template_name.':'.'probe.configvalue[RSM.IP'.$ipv.'.ENABLED]'.'.last(0)}=1',
		     'priority' => '2',
	};

	create_trigger($options);
    }

    $options = { 'description' => 'DNS-RTT-'.$proto_uc.' {HOST.NAME}: 5.1.1 Step 5 - No reply from resolver',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_DNS_RES_NOREPLY.
                                            '&{'.$template_name.':'.'probe.configvalue[RSM.IP'.$ipv.'.ENABLED]'.'.last(0)}=1',
			'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => 'DNS-RTT-'.$proto_uc.' {HOST.NAME}: 5.1.1 Step 2 - AD bit is missing from '.$ns_name.' ['.$ip.']',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_DNS_RES_NOADBIT.
                                            '&{'.$template_name.':'.'probe.configvalue[RSM.IP'.$ipv.'.ENABLED]'.'.last(0)}=1',
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
    if ($value_type == VALUE_TYPE_AVAIL)
    {
	$options = {'name' => $name,
                                              'key_'=> $key,
                                              'hostid' => $hostid,
                                              'type' => 2, 'value_type' => 3,
					      'applications' => $applicationids,
					      'valuemapid' => rsm_value_mappings->{'rsm_avail'}};
    }
    elsif ($value_type == VALUE_TYPE_NUM)
    {
	$options = {'name' => $name,
                                              'key_'=> $key,
                                              'hostid' => $hostid,
                                              'type' => 2, 'value_type' => 3,
					      'applications' => $applicationids};
    }
    elsif ($value_type == VALUE_TYPE_PERC) {
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
    my $template_name = shift;

    my $proto_uc = 'UDP';

    my $options = {'name' => 'DNS update time of $2 ($3)',
                                              'key_'=> 'rsm.dns.udp.upd[{$RSM.TLD},'.$ns_name.','.$ip.']',
                                              'hostid' => $templateid,
                                              'applications' => [get_application_id('DNS RTT ('.$proto_uc.')', $templateid)],
                                              'type' => 2, 'value_type' => 0,
                                              'valuemapid' => rsm_value_mappings->{'rsm_dns'},
		                              'status' => (defined($OPTS{'epp-servers'}) ? 0 : 1)};
    return create_item($options);
}

sub create_items_dns {
    my $templateid = shift;
    my $template_name = shift;

    my $proto = 'tcp';
    my $proto_uc = uc($proto);
    my $item_key = 'rsm.dns.'.$proto.'[{$RSM.TLD}]';

    my $options = {'name' => 'Number of working DNS Name Servers of $1 ('.$proto_uc.')',
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [get_application_id('DNS ('.$proto_uc.')', $templateid)],
                                              'type' => 3, 'value_type' => 3,
                                              'delay' => $cfg_global_macros->{'{$RSM.DNS.TCP.DELAY}'}};

    create_item($options);

    $options = { 'description' => 'DNS-'.$proto_uc.' {HOST.NAME}: 5.2.3 - Less than {$RSM.DNS.AVAIL.MINNS} NS servers have answered succesfully',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}<{$RSM.DNS.AVAIL.MINNS}',
			'priority' => '4',
                };

    create_trigger($options);

    $proto = 'udp';
    $proto_uc = uc($proto);
    $item_key = 'rsm.dns.'.$proto.'[{$RSM.TLD}]';

    $options = {'name' => 'Number of working DNS Name Servers of $1 ('.$proto_uc.')',
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [get_application_id('DNS ('.$proto_uc.')', $templateid)],
                                              'type' => 3, 'value_type' => 3,
                                              'delay' => $cfg_global_macros->{'{$RSM.DNS.UDP.DELAY}'}, 'valuemapid' => rsm_value_mappings->{'rsm_dns'}};

    create_item($options);

    $options = { 'description' => 'DNS-'.$proto_uc.' {HOST.NAME}: 5.2.3 - Less than {$RSM.DNS.AVAIL.MINNS} NS servers have answered succesfully',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}<{$RSM.DNS.AVAIL.MINNS}',
			'priority' => '4',
                };

    create_trigger($options);
}

sub create_items_rdds {
    my $templateid = shift;
    my $template_name = shift;

    my $applicationid_43 = get_application_id('RDDS43', $templateid);
    my $applicationid_80 = get_application_id('RDDS80', $templateid);

    my $item_key = 'rsm.rdds.43.ip[{$RSM.TLD}]';

    my $options = {'name' => 'RDDS43 IP of $1',
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [$applicationid_43],
                                              'type' => 2, 'value_type' => 1,
                                              'valuemapid' => rsm_value_mappings->{'rsm_rdds_rttudp'}};
    create_item($options);

    $item_key = 'rsm.rdds.43.rtt[{$RSM.TLD}]';

    $options = {'name' => 'RDDS43 RTT of $1',
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [$applicationid_43],
                                              'type' => 2, 'value_type' => 0,
                                              'valuemapid' => rsm_value_mappings->{'rsm_rdds_rttudp'}};
    create_item($options);

    $options = { 'description' => 'RDDS43-RTT {HOST.NAME}: Internal error',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_INTERNAL,
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => 'RDDS43-RTT {HOST.NAME}: 6.1.1 Step 5 - No reply from the server',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_RDDS43_NOREPLY,
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => 'RDDS43-RTT {HOST.NAME}: 6.1.1 Step 5 - The server output does not contain "{$RSM.RDDS.NS.STRING}"',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_RDDS43_NONS,
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => 'RDDS43-RTT {HOST.NAME}: 6.1.1 Step 6 - UNIX timestamp is missing in reply from the server',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_RDDS43_NOTS,
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => 'RDDS43-RTT {HOST.NAME}: 6.1.1 Step 6 - Invalid UNIX timestamp in reply from the server',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_RDDS43_ERRTS,
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => 'RDDS43-RTT {HOST.NAME}: 6.1.1 Step 2 - Cannot resolve a host',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_RDDS_ERRRES,
                        'priority' => '2',
                };

    create_trigger($options);

    if (defined($OPTS{'epp-servers'})) {
	$item_key = 'rsm.rdds.43.upd[{$RSM.TLD}]';

	$options = {'name' => 'RDDS43 update time of $1',
		    'key_'=> $item_key,
		    'hostid' => $templateid,
		    'applications' => [$applicationid_43],
		    'type' => 2, 'value_type' => 0,
		    'valuemapid' => rsm_value_mappings->{'rsm_rdds_rttudp'},
		    'status' => 0};
	create_item($options);

	$options = { 'description' => 'RDDS43-UPD {HOST.NAME}: No UNIX timestamp',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_RDDS43_NOTS,
                        'priority' => '2',
                };

	create_trigger($options);
    }

    $item_key = 'rsm.rdds.80.ip[{$RSM.TLD}]';

    $options = {'name' => 'RDDS80 IP of $1',
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [$applicationid_80],
                                              'type' => 2, 'value_type' => 1};
    create_item($options);

    $item_key = 'rsm.rdds.80.rtt[{$RSM.TLD}]';

    $options = {'name' => 'RDDS80 RTT of $1',
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [$applicationid_80],
                                              'type' => 2, 'value_type' => 0,
                                              'valuemapid' => rsm_value_mappings->{'rsm_rdds_rttudp'}};
    create_item($options);

    $options = { 'description' => 'RDDS80-RTT {HOST.NAME}: Internal error',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_INTERNAL,
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => 'RDDS80-RTT {HOST.NAME}: 6.1.1 Step 5 - No reply from the server',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_RDDS80_NOREPLY,
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => 'RDDS80-RTT {HOST.NAME}: 6.1.1 Step 2 - Cannot resolve a host',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_RDDS_ERRRES,
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => 'RDDS80-RTT {HOST.NAME}: 6.1.1 Step 2 - Cannot get HTTP response code from the server',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_RDDS80_NOHTTPCODE,
                        'priority' => '2',
                };

    create_trigger($options);

    $options = { 'description' => 'RDDS80-RTT {HOST.NAME}: 6.1.1 Step 2 - Invalid HTTP response code from the server',
                         'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_RDDS80_EHTTPCODE,
                        'priority' => '2',
                };

    create_trigger($options);

    $item_key = 'rsm.rdds[{$RSM.TLD},"'.$OPTS{'rdds43-servers'}.'","'.$OPTS{'rdds80-servers'}.'"]';

    $options = {'name' => 'RDDS availability of $1',
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [get_application_id('RDDS', $templateid)],
                                              'type' => 3, 'value_type' => 3,
					      'delay' => $cfg_global_macros->{'{$RSM.RDDS.DELAY}'},
                                              'valuemapid' => rsm_value_mappings->{'rsm_rdds_avail'}};
    create_item($options);
}

sub create_items_epp {
    my $templateid = shift;
    my $template_name = shift;

    my $applicationid = get_application_id('EPP', $templateid);

    my ($item_key, $options);

    $item_key = 'rsm.epp[{$RSM.TLD},"'.$OPTS{'epp-servers'}.'"]';

    $options = {'name' => 'EPP service availability at $1 ($2)',
		'key_'=> $item_key,
		'hostid' => $templateid,
		'applications' => [$applicationid],
		'type' => 3, 'value_type' => 3,
		'delay' => $cfg_global_macros->{'{$RSM.EPP.DELAY}'}, 'valuemapid' => rsm_value_mappings->{'rsm_avail'}};

    create_item($options);

    $item_key = 'rsm.epp.ip[{$RSM.TLD}]';

    $options = {'name' => 'EPP IP of $1',
		'key_'=> $item_key,
		'hostid' => $templateid,
		'applications' => [$applicationid],
		'type' => 2, 'value_type' => 1};

    create_item($options);

    $item_key = 'rsm.epp.rtt[{$RSM.TLD},login]';

    $options = {'name' => 'EPP $2 command RTT of $1',
		'key_'=> $item_key,
		'hostid' => $templateid,
		'applications' => [$applicationid],
		'type' => 2, 'value_type' => 0,
		'valuemapid' => rsm_value_mappings->{'rsm_epp'}};

    create_item($options);

    $item_key = 'rsm.epp.rtt[{$RSM.TLD},update]';

    $options = {'name' => 'EPP $2 command RTT of $1',
		'key_'=> $item_key,
		'hostid' => $templateid,
		'applications' => [$applicationid],
		'type' => 2, 'value_type' => 0,
		'valuemapid' => rsm_value_mappings->{'rsm_epp'}};

    create_item($options);

    $item_key = 'rsm.epp.rtt[{$RSM.TLD},info]';

    $options = {'name' => 'EPP $2 command RTT of $1',
		'key_'=> $item_key,
		'hostid' => $templateid,
		'applications' => [$applicationid],
		'type' => 2, 'value_type' => 0,
		'valuemapid' => rsm_value_mappings->{'rsm_epp'}};

    create_item($options);

    $options = { 'description' => 'EPP-INFO {HOST.NAME}: Internal error',
                 'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_INTERNAL,
                'priority' => '2',
    };

    create_trigger($options);

    $options = { 'description' => 'EPP-INFO {HOST.NAME}: 7.1.1 Step 2 - IP is missing',
                 'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_EPP_NO_IP,
                'priority' => '2',
    };

    create_trigger($options);

    $options = { 'description' => 'EPP-INFO {HOST.NAME}: 7.1.1 Step 4 - Cannot connect to the server',
                 'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_EPP_CONNECT,
                'priority' => '2',
    };

    create_trigger($options);

    $options = { 'description' => 'EPP-INFO {HOST.NAME}: 7.1.1 Step 5 - Invalid certificate or private key',
                 'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_EPP_CRYPT,
                'priority' => '2',
    };

    create_trigger($options);

    $options = { 'description' => 'EPP-INFO {HOST.NAME}: 7.1.1 Step 5 - First message timeout',
                 'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_EPP_FIRSTTO,
                'priority' => '2',
    };

    create_trigger($options);

    $options = { 'description' => 'EPP-INFO {HOST.NAME}: 7.1.1 Step 5 - First message is invalid',
                 'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_EPP_FIRSTINVAL,
                'priority' => '2',
    };

    create_trigger($options);

    $options = { 'description' => 'EPP-INFO {HOST.NAME}: 7.1.1 Step 6 - LOGIN command timeout',
                 'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_EPP_LOGINTO,
                'priority' => '2',
    };

    create_trigger($options);

    $options = { 'description' => 'EPP-INFO {HOST.NAME}: 7.1.1 Step 6 - Invalid reply to LOGIN command',
                 'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_EPP_LOGININVAL,
                'priority' => '2',
    };

    create_trigger($options);

    $options = { 'description' => 'EPP-INFO {HOST.NAME}: 7.1.1 Step 7 - UPDATE command timeout',
                 'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_EPP_UPDATETO,
                'priority' => '2',
    };

    create_trigger($options);

    $options = { 'description' => 'EPP-INFO {HOST.NAME}: 7.1.1 Step 7 - Invalid reply to UPDATE command',
                 'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_EPP_UPDATEINVAL,
                'priority' => '2',
    };

    create_trigger($options);

    $options = { 'description' => 'EPP-INFO {HOST.NAME}: 7.1.1 Step 7 - INFO command timeout',
                 'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_EPP_INFOTO,
                'priority' => '2',
    };

    create_trigger($options);

    $options = { 'description' => 'EPP-INFO {HOST.NAME}: 7.1.1 Step 7 - Invalid reply to INFO command',
                 'expression' => '{'.$template_name.':'.$item_key.'.last(0)}='.ZBX_EC_EPP_INFOINVAL,
                'priority' => '2',
    };

    create_trigger($options);
}


sub trim
{
    $_[0] =~ s/^\s*//g;
    $_[0] =~ s/\s*$//g;
}

sub get_sensdata
{
    my $prompt = shift;

    my $sensdata;

    print($prompt);
    system('stty', '-echo');
    chop($sensdata = <STDIN>);
    system('stty', 'echo');
    print("\n");

    return $sensdata;
}

sub exp_get_keysalt
{
    my $self = shift;

    if ($self->match() =~ m/^([^\s]+\|[^\s]+)/)
    {
	$exp_output = $1;
    }
}

sub get_encrypted_passwd
{
    my $keysalt = shift;
    my $passphrase = shift;
    my $passwd = shift;

    my @params = split('\|', $keysalt);

    pfail("$keysalt: invalid keysalt") unless (scalar(@params) == 2);

    push(@params, '-n');

    my $exp = new Expect or pfail("cannot create Expect object");
    $exp->raw_pty(1);
    $exp->spawn($exp_command, @params) or pfail("cannot spawn $exp_command: $!");

    $exp->send("$passphrase\n");
    $exp->send("$passwd\n");

    print("");
    $exp->expect($exp_timeout, [qr/.*\n/, \&exp_get_keysalt]);

    $exp->soft_close();

    pfail("$exp_command returned error") unless ($exp_output and $exp_output =~ m/\|/);

    my $ret = $exp_output;
    $exp_output = undef;

    return $ret;
}

sub get_encrypted_privkey
{
    my $keysalt = shift;
    my $passphrase = shift;
    my $file = shift;

    my @params = split('\|', $keysalt);

    pfail("$keysalt: invalid keysalt") unless (scalar(@params) == 2);

    push(@params, '-n', '-f', $file);

    my $exp = new Expect or pfail("cannot create Expect object");
    $exp->raw_pty(1);
    $exp->spawn($exp_command, @params) or pfail("cannot spawn $exp_command: $!");

    $exp->send("$passphrase\n");

    print("");
    $exp->expect($exp_timeout, [qr/.*\n/, \&exp_get_keysalt]);

    $exp->soft_close();

    pfail("$exp_command returned error") unless ($exp_output and $exp_output =~ m/\|/);

    my $ret = $exp_output;
    $exp_output = undef;

    return $ret;
}

sub read_file {
    my $file = shift;

    my $contents = do {
	local $/ = undef;
	open my $fh, "<", $file or pfail("could not open $file: $!");
	<$fh>;
    };

    return $contents;
}

sub get_md5 {
    my $file = shift;

    my $contents = do {
        local $/ = undef;
        open(my $fh, "<", $file) or pfail("cannot open $file: $!");
        <$fh>;
    };

    my $index = index($contents, "-----BEGIN CERTIFICATE-----");
    pfail("specified file $file does not contain line \"-----BEGIN CERTIFICATE-----\"") if ($index == -1);

    return md5_hex(substr($contents, $index));
}

sub create_main_template {
    my $tld = shift;
    my $ns_servers = shift;

    my $template_name = 'Template '.$tld;

    my $templateid = create_template($template_name);

    pfail("Could not create main template for '".$tld."' TLD. ".$templateid->{'data'}) if check_api_error($templateid) eq true;

    my $delay = 300;
    my $appid = get_application_id('Configuration', $templateid);
    my ($options, $key);

    foreach my $m ('RSM.IP4.ENABLED', 'RSM.IP6.ENABLED') {
        $key = 'probe.configvalue['.$m.']';

        $options = {'name' => 'Value of $1 variable',
                    'key_'=> $key,
                    'hostid' => $templateid,
                    'applications' => [$appid],
                    'params' => '{$'.$m.'}',
                    'delay' => $delay,
                    'type' => ITEM_TYPE_CALCULATED, 'value_type' => ITEM_VALUE_TYPE_UINT64};

        my $itemid = create_item($options);

	print $itemid->{'data'}."\n" if check_api_error($itemid) eq true;
    }

    foreach my $ns_name (sort keys %{$ns_servers}) {
	print $ns_name."\n";

        my @ipv4 = defined(@{$ns_servers->{$ns_name}{'v4'}}) ? @{$ns_servers->{$ns_name}{'v4'}} : undef;
	my @ipv6 = defined(@{$ns_servers->{$ns_name}{'v6'}}) ? @{$ns_servers->{$ns_name}{'v6'}} : undef;

        foreach (my $i_ipv4 = 0; $i_ipv4 <= $#ipv4; $i_ipv4++) {
	    next unless defined $ipv4[$i_ipv4];
	    print "	--v4     $ipv4[$i_ipv4]\n";

            create_item_dns_rtt($ns_name, $ipv4[$i_ipv4], $templateid, $template_name, "tcp", '4');
	    create_item_dns_rtt($ns_name, $ipv4[$i_ipv4], $templateid, $template_name, "udp", '4');
	    if (defined($OPTS{'epp-servers'})) {
    		create_item_dns_udp_upd($ns_name, $ipv4[$i_ipv4], $templateid);

		my $options = { 'description' => 'DNS-UPD-UDP {HOST.NAME}: No UNIX timestamp for ['.$ipv4[$i_ipv4].']',
            	             'expression' => '{'.$template_name.':'.'rsm.dns.udp.upd[{$RSM.TLD},'.$ns_name.','.$ipv4[$i_ipv4].']'.'.last(0)}='.ZBX_EC_DNS_NS_NOTS.
					     '&{'.$template_name.':'.'probe.configvalue[RSM.IP4.ENABLED]'.'.last(0)}=1',
                	    'priority' => '2',
                };

	        create_trigger($options);
    	    }
        }

	foreach (my $i_ipv6 = 0; $i_ipv6 <= $#ipv6; $i_ipv6++) {
	    next unless defined $ipv6[$i_ipv6];
    	    print "	--v6     $ipv6[$i_ipv6]\n";

	    create_item_dns_rtt($ns_name, $ipv6[$i_ipv6], $templateid, $template_name, "tcp", '6');
    	    create_item_dns_rtt($ns_name, $ipv6[$i_ipv6], $templateid, $template_name, "udp", '6');
	    if (defined($OPTS{'epp-servers'})) {
    		create_item_dns_udp_upd($ns_name, $ipv6[$i_ipv6], $templateid);

		my $options = { 'description' => 'DNS-UPD-UDP {HOST.NAME}: No UNIX timestamp for ['.$ipv6[$i_ipv6].']',
                             'expression' => '{'.$template_name.':'.'rsm.dns.udp.upd[{$RSM.TLD},'.$ns_name.','.$ipv6[$i_ipv6].']'.'.last(0)}='.ZBX_EC_DNS_NS_NOTS.
						'&{'.$template_name.':'.'probe.configvalue[RSM.IP6.ENABLED]'.'.last(0)}=1',
                            'priority' => '2',
                };

                create_trigger($options);
	    }
        }
    }

    create_items_dns($templateid, $template_name);
    create_items_rdds($templateid, $template_name) if (defined($OPTS{'rdds43-servers'}));
    create_items_epp($templateid, $template_name) if (defined($OPTS{'epp-servers'}));

    create_macro('{$RSM.TLD}', $tld, $templateid);
    create_macro('{$RSM.DNS.TESTPREFIX}', $OPTS{'dns-test-prefix'}, $templateid);
    create_macro('{$RSM.RDDS.TESTPREFIX}', $OPTS{'rdds-test-prefix'}, $templateid) if (defined($OPTS{'rdds-test-prefix'}));
    create_macro('{$RSM.RDDS.NS.STRING}', defined($OPTS{'rdds-ns-string'}) ? $OPTS{'rdds-ns-string'} : cfg_default_rdds_ns_string, $templateid);
    create_macro('{$RSM.TLD.DNSSEC.ENABLED}', defined($OPTS{'dnssec'}) ? 1 : 0, $templateid);
    create_macro('{$RSM.TLD.RDDS.ENABLED}', defined($OPTS{'rdds43-servers'}) ? 1 : 0, $templateid);
    create_macro('{$RSM.TLD.EPP.ENABLED}', defined($OPTS{'epp-servers'}) ? 1 : 0, $templateid);

    if ($OPTS{'epp-servers'})
    {
	my $m = '{$RSM.EPP.KEYSALT}';
	my $keysalt = get_global_macro_value($m);
	pfail('cannot get macro ', $m) unless defined($keysalt);
	trim($keysalt);
	pfail("global macro $m must conatin |") unless ($keysalt =~ m/\|/);

	if ($OPTS{'epp-commands'}) {
	    create_macro('{$RSM.EPP.COMMANDS}', $OPTS{'epp-commands'}, $templateid, 1);
	} else {
	    create_macro('{$RSM.EPP.COMMANDS}', '/opt/test-sla/epp-commands/'.$tld, $templateid);
	}
	create_macro('{$RSM.EPP.USER}', $OPTS{'epp-user'}, $templateid, 1);
	create_macro('{$RSM.EPP.CERT}', encode_base64(read_file($OPTS{'epp-cert'}), ''),  $templateid, 1);
	create_macro('{$RSM.EPP.SERVERID}', $OPTS{'epp-serverid'}, $templateid, 1);
	create_macro('{$RSM.EPP.TESTPREFIX}', $OPTS{'epp-test-prefix'}, $templateid, 1);
	create_macro('{$RSM.EPP.SERVERCERTMD5}', get_md5($OPTS{'epp-servercert'}), $templateid, 1);

	my $passphrase = get_sensdata("Enter EPP secret key passphrase: ");
	my $passwd = get_sensdata("Enter EPP password: ");
	create_macro('{$RSM.EPP.PASSWD}', get_encrypted_passwd($keysalt, $passphrase, $passwd), $templateid, 1);
	$passwd = undef;
	create_macro('{$RSM.EPP.PRIVKEY}', get_encrypted_privkey($keysalt, $passphrase, $OPTS{'epp-privkey'}), $templateid, 1);
	$passphrase = undef;

	print("EPP data saved successfully.\n");
    }

    return $templateid;
}

sub create_all_slv_ns_items {
    my $ns_name = shift;
    my $ip = shift;
    my $hostid = shift;

    create_slv_item('% of successful monthly DNS resolution RTT (UDP): $1 ($2)', 'rsm.slv.dns.ns.rtt.udp.month['.$ns_name.','.$ip.']', $hostid, VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]);
    create_slv_item('% of successful monthly DNS resolution RTT (TCP): $1 ($2)', 'rsm.slv.dns.ns.rtt.tcp.month['.$ns_name.','.$ip.']', $hostid, VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]);
    create_slv_item('% of successful monthly DNS update time: $1 ($2)', 'rsm.slv.dns.ns.upd.month['.$ns_name.','.$ip.']', $hostid, VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]) if (defined($OPTS{'epp-servers'}));
    create_slv_item('DNS NS availability: $1 ($2)', 'rsm.slv.dns.ns.avail['.$ns_name.','.$ip.']', $hostid, VALUE_TYPE_AVAIL, [get_application_id(APP_SLV_PARTTEST, $hostid)]);
    create_slv_item('DNS NS minutes of downtime: $1 ($2)', 'rsm.slv.dns.ns.downtime['.$ns_name.','.$ip.']', $hostid, VALUE_TYPE_NUM, [get_application_id(APP_SLV_CURMON, $hostid)]);
    create_slv_item('DNS NS probes that returned results: $1 ($2)', 'rsm.slv.dns.ns.results['.$ns_name.','.$ip.']', $hostid, VALUE_TYPE_NUM, [get_application_id(APP_SLV_CURMON, $hostid)]);
    create_slv_item('DNS NS probes that returned positive results: $1 ($2)', 'rsm.slv.dns.ns.positive['.$ns_name.','.$ip.']', $hostid, VALUE_TYPE_NUM, [get_application_id(APP_SLV_CURMON, $hostid)]);
    create_slv_item('DNS NS positive results by SLA: $1 ($2)', 'rsm.slv.dns.ns.sla['.$ns_name.','.$ip.']', $hostid, VALUE_TYPE_NUM, [get_application_id(APP_SLV_CURMON, $hostid)]);
    create_slv_item('% of monthly DNS NS availability: $1 ($2)', 'rsm.slv.dns.ns.month['.$ns_name.','.$ip.']', $hostid, VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]);
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

    create_slv_item('DNS availability', 'rsm.slv.dns.avail', $hostid, VALUE_TYPE_AVAIL, [get_application_id(APP_SLV_PARTTEST, $hostid)]);
    create_slv_item('DNS minutes of downtime', 'rsm.slv.dns.downtime', $hostid, VALUE_TYPE_NUM, [get_application_id(APP_SLV_CURMON, $hostid)]);

    my $options;

    # NB! Configuration trigger that is used in PHP and C code to detect incident!
    # priority must be set to 0!
    $options = { 'description' => 'DNS-AVAIL {HOST.NAME}: 5.2.4 - The service is not available',
                         'expression' => '({TRIGGER.VALUE}=0&'.
						'{'.$host_name.':rsm.slv.dns.avail.count(#{$RSM.INCIDENT.DNS.FAIL},0,"eq")}={$RSM.INCIDENT.DNS.FAIL})|'.
					 '({TRIGGER.VALUE}=1&'.
						'{'.$host_name.':rsm.slv.dns.avail.count(#{$RSM.INCIDENT.DNS.RECOVER},0,"ne")}<{$RSM.INCIDENT.DNS.RECOVER})',
                        'priority' => '0',
                };

    create_trigger($options);

    create_slv_item('DNS weekly unavailability', 'rsm.slv.dns.rollweek', $hostid, VALUE_TYPE_PERC, [get_application_id(APP_SLV_ROLLWEEK, $hostid)]);

    my $depend_down;

    foreach my $position (sort keys %{$trigger_rollweek_thresholds}) {
	my $threshold = $trigger_rollweek_thresholds->{$position}->{'threshold'};
	my $priority = $trigger_rollweek_thresholds->{$position}->{'priority'};
        next if ($threshold eq 0);

        $options = { 'description' => 'DNS-ROLLWEEK {HOST.NAME}: 5.2.5 - The Service Availability [{ITEM.LASTVALUE1}] > '.$threshold.'%',
                         'expression' => '{'.$host_name.':rsm.slv.dns.rollweek.last(0)}='.$threshold.'|'.
                                        '{'.$host_name.':rsm.slv.dns.rollweek.last(0)}>'.$threshold,
                        'priority' => $priority,
                };

        my $result = create_trigger($options);

	my $triggerid = $result->{'triggerids'}[0];

        if (defined($depend_down)) {
            add_dependency($triggerid, $depend_down);
        }

        $depend_down = $triggerid;
    }

    undef($depend_down);

    if (defined($OPTS{'dnssec'})) {
	create_slv_item('DNSSEC availability', 'rsm.slv.dnssec.avail', $hostid, VALUE_TYPE_AVAIL, [get_application_id(APP_SLV_PARTTEST, $hostid)]);

	# NB! Configuration trigger that is used in PHP and C code to detect incident!
	# priority must be set to 0!
	$options = { 'description' => 'DNSSEC-AVAIL {HOST.NAME}: 5.3.3 - The service is not available',
		     'expression' => '({TRIGGER.VALUE}=0&'.
			 '{'.$host_name.':rsm.slv.dnssec.avail.count(#{$RSM.INCIDENT.DNSSEC.FAIL},0,"eq")}={$RSM.INCIDENT.DNSSEC.FAIL})|'.
			 '({TRIGGER.VALUE}=1&'.
			 '{'.$host_name.':rsm.slv.dnssec.avail.count(#{$RSM.INCIDENT.DNSSEC.RECOVER},0,"ne")}<{$RSM.INCIDENT.DNSSEC.RECOVER})',
			 'priority' => '0',
	};

	create_trigger($options);

	create_slv_item('DNSSEC weekly unavailability', 'rsm.slv.dnssec.rollweek', $hostid, VALUE_TYPE_PERC, [get_application_id(APP_SLV_ROLLWEEK, $hostid)]);

        my $depend_down;

	foreach my $position (sort keys %{$trigger_rollweek_thresholds}) {
    	    my $threshold = $trigger_rollweek_thresholds->{$position}->{'threshold'};
    	    my $priority = $trigger_rollweek_thresholds->{$position}->{'priority'};
    	    next if ($threshold eq 0);

            $options = { 'description' => 'DNSSEC-ROLLWEEK {HOST.NAME}: 5.3.4 - Proper resolution [{ITEM.LASTVALUE1}] >'.$threshold.'%',
                         'expression' => '{'.$host_name.':rsm.slv.dnssec.rollweek.last(0)}>'.$threshold.'|'.
					    '{'.$host_name.':rsm.slv.dnssec.rollweek.last(0)}='.$threshold,
                        'priority' => $priority,
                };

	    my $result = create_trigger($options);

    	    my $triggerid = $result->{'triggerids'}[0];

	    if (defined($depend_down)) {
    	        add_dependency($triggerid, $depend_down);
    	    }

    	    $depend_down = $triggerid;
        }

	undef($depend_down);
    }


    if (defined($OPTS{'rdds43-servers'})) {
	create_slv_item('RDDS availability', 'rsm.slv.rdds.avail', $hostid, VALUE_TYPE_AVAIL, [get_application_id(APP_SLV_PARTTEST, $hostid)]);
	create_slv_item('RDDS minutes of downtime', 'rsm.slv.rdds.downtime', $hostid, VALUE_TYPE_NUM, [get_application_id(APP_SLV_CURMON, $hostid)]);

	# NB! Configuration trigger that is used in PHP and C code to detect incident!
	# priority must be set to 0!
	$options = { 'description' => 'RDDS-AVAIL {HOST.NAME}: 6.2.3 - The service is not available',
		     'expression' => '({TRIGGER.VALUE}=0&'.
			 '{'.$host_name.':rsm.slv.rdds.avail.count(#{$RSM.INCIDENT.RDDS.FAIL},0,"eq")}={$RSM.INCIDENT.RDDS.FAIL})|'.
			 '({TRIGGER.VALUE}=1&'.
			 '{'.$host_name.':rsm.slv.rdds.avail.count(#{$RSM.INCIDENT.RDDS.RECOVER},0,"ne")}<{$RSM.INCIDENT.RDDS.RECOVER})',
			 'priority' => '0',
	};

	create_trigger($options);

	create_slv_item('RDDS weekly unavailability', 'rsm.slv.rdds.rollweek', $hostid, VALUE_TYPE_PERC, [get_application_id(APP_SLV_ROLLWEEK, $hostid)]);

        my $depend_down;

	foreach my $position (sort keys %{$trigger_rollweek_thresholds}) {
    	    my $threshold = $trigger_rollweek_thresholds->{$position}->{'threshold'};
    	    my $priority = $trigger_rollweek_thresholds->{$position}->{'priority'};
    	    next if ($threshold eq 0);

            $options = { 'description' => 'RDDS-ROLLWEEK {HOST.NAME}: 6.2.4 - The Service Availability [{ITEM.LASTVALUE1}] >'.$threshold.'%',
                         'expression' => '{'.$host_name.':rsm.slv.rdds.rollweek.last(0)}>'.$threshold.'|'.
					    '{'.$host_name.':rsm.slv.rdds.rollweek.last(0)}='.$threshold,
                        'priority' => $priority,
                };

	    my $result = create_trigger($options);

    	    my $triggerid = $result->{'triggerids'}[0];

	    if (defined($depend_down)) {
    	        add_dependency($triggerid, $depend_down);
    	    }

    	    $depend_down = $triggerid;
        }

	undef($depend_down);


	create_slv_item('% of successful monthly RDDS43 resolution RTT', 'rsm.slv.rdds.43.rtt.month', $hostid, VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]);
	create_slv_item('% of successful monthly RDDS80 resolution RTT', 'rsm.slv.rdds.80.rtt.month', $hostid, VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]);
	create_slv_item('% of successful monthly RDDS update time', 'rsm.slv.rdds.upd.month', $hostid, VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]) if (defined($OPTS{'epp-servers'}));
    }

    if (defined($OPTS{'epp-servers'})) {
	create_slv_item('EPP availability', 'rsm.slv.epp.avail', $hostid, VALUE_TYPE_AVAIL, [get_application_id(APP_SLV_PARTTEST, $hostid)]);
	create_slv_item('EPP minutes of downtime', 'rsm.slv.epp.downtime', $hostid, VALUE_TYPE_NUM, [get_application_id(APP_SLV_CURMON, $hostid)]);
	create_slv_item('EPP weekly unavailability', 'rsm.slv.epp.rollweek', $hostid, VALUE_TYPE_PERC, [get_application_id(APP_SLV_ROLLWEEK, $hostid)]);

	create_slv_item('% of successful monthly EPP LOGIN resolution RTT', 'rsm.slv.epp.rtt.login.month', $hostid, VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]);
	create_slv_item('% of successful monthly EPP UPDATE resolution RTT', 'rsm.slv.epp.rtt.update.month', $hostid, VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]);
	create_slv_item('% of successful monthly EPP INFO resolution RTT', 'rsm.slv.epp.rtt.info.month', $hostid, VALUE_TYPE_PERC, [get_application_id(APP_SLV_MONTHLY, $hostid)]);

	# NB! Configuration trigger that is used in PHP and C code to detect incident!
	# priority must be set to 0!
	$options = { 'description' => 'EPP-AVAIL {HOST.NAME}: 7.2.3 - The service is not available',
		     'expression' => '({TRIGGER.VALUE}=0&'.
			 '{'.$host_name.':rsm.slv.epp.avail.count(#{$RSM.INCIDENT.EPP.FAIL},0,"eq")}={$RSM.INCIDENT.EPP.FAIL})|'.
			 '({TRIGGER.VALUE}=1&'.
			 '{'.$host_name.':rsm.slv.epp.avail.count(#{$RSM.INCIDENT.EPP.RECOVER},0,"ne")}<{$RSM.INCIDENT.EPP.RECOVER})',
			 'priority' => '0',
	};

	create_trigger($options);

        my $depend_down;

	foreach my $position (sort keys %{$trigger_rollweek_thresholds}) {
    	    my $threshold = $trigger_rollweek_thresholds->{$position}->{'threshold'};
    	    my $priority = $trigger_rollweek_thresholds->{$position}->{'priority'};
    	    next if ($threshold eq 0);

            $options = { 'description' => 'EPP-ROLLWEEK {HOST.NAME}: 7.2.4 - The Service Availability [{ITEM.LASTVALUE1}] >'.$threshold.'%',
                         'expression' => '{'.$host_name.':rsm.slv.epp.rollweek.last(0)}>'.$threshold.'|'.
					    '{'.$host_name.':rsm.slv.epp.rollweek.last(0)}='.$threshold,
                        'priority' => $priority,
                };

	    my $result = create_trigger($options);

    	    my $triggerid = $result->{'triggerids'}[0];

	    if (defined($depend_down)) {
    	        add_dependency($triggerid, $depend_down);
    	    }

    	    $depend_down = $triggerid;
        }

	undef($depend_down);
    }
}

# calculated items, configuration history (TODO: rename host to something like config_history)
sub create_rsm_items {
    my $hostid = shift;

    my $options;
    my $appid = get_application_id('Configuration', $hostid);

    my $macros = {
		&TIME_MINUTE => [
			'RSM.INCIDENT.DNS.FAIL',
    			'RSM.INCIDENT.DNS.RECOVER',
		        'RSM.INCIDENT.DNSSEC.FAIL',
		        'RSM.INCIDENT.DNSSEC.RECOVER',
		        'RSM.INCIDENT.RDDS.FAIL',
		        'RSM.INCIDENT.RDDS.RECOVER',
		        'RSM.INCIDENT.EPP.FAIL',
		        'RSM.INCIDENT.EPP.RECOVER',
		        'RSM.DNS.UDP.DELAY',
		        'RSM.RDDS.DELAY',
		        'RSM.EPP.DELAY',
		        'RSM.DNS.UDP.RTT.HIGH',
		        'RSM.DNS.AVAIL.MINNS',
		        'RSM.DNS.ROLLWEEK.SLA',
		        'RSM.RDDS.ROLLWEEK.SLA',
		        'RSM.EPP.ROLLWEEK.SLA'
		],
		&TIME_DAY => [
			'RSM.SLV.DNS.UDP.RTT',
		        'RSM.SLV.DNS.TCP.RTT',
		        'RSM.SLV.NS.AVAIL',
		        'RSM.SLV.RDDS43.RTT',
		        'RSM.SLV.RDDS80.RTT',
		        'RSM.SLV.RDDS.UPD',
		        'RSM.SLV.DNS.NS.UPD',
		        'RSM.SLV.EPP.LOGIN',
		        'RSM.SLV.EPP.UPDATE',
		        'RSM.SLV.EPP.INFO'
		]};

    foreach my $delay (keys %{$macros}) {
	foreach my $macro (@{$macros->{$delay}}) {
	    $options = {'name' => '$1 value',
                   'key_'=> 'rsm.configvalue['.$macro.']',
                   'hostid' => $hostid,
                   'applications' => [$appid],
                   'params' => '{$'.$macro.'}',
                   'delay' => $delay,
                   'type' => ITEM_TYPE_CALCULATED, 'value_type' => ITEM_VALUE_TYPE_UINT64};

    	    my $itemid = create_item($options);

	    pfail($itemid->{'data'}) if check_api_error($itemid) eq true;
	}
    }
}

sub usage {
    my ($opt_name, $opt_value) = @_;

    my $cfg_default_rdds_ns_string = cfg_default_rdds_ns_string;

    print <<EOF;

    Usage: $0 [options]

Required options

        --tld=STRING
                TLD name
        --dns-test-prefix=STRING
                domain test prefix for DNS monitoring (specify '*randomtld*' for root servers monitoring)

Other options
        --type=STRING
                Type of TLD. Possible values: @{[TLD_TYPE_G]}, @{[TLD_TYPE_CC]}, @{[TLD_TYPE_OTHER]}, @{[TLD_TYPE_TEST]}.
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
        --epp-servers=STRING
                list of EPP servers separated by comma: "NAME1,NAME2,..."
        --epp-user
                specify EPP username
	--epp-cert
                path to EPP Client certificates file
	--epp-servercert
                path to EPP Server certificates file
	--epp-privkey
                path to EPP Client private key file (unencrypted)
	--epp-serverid
                specify expected EPP Server ID string in reply
	--epp-test-prefix=STRING
                this string represents DOMAIN (in DOMAIN.TLD) to use in EPP commands
	--epp-commands
                path to a directory on the Probe Node containing EPP command templates
		(default: /opt/test-sla/epp-commands/TLD)
        --rdds-ns-string=STRING
                name server prefix in the WHOIS output
		(default: $cfg_default_rdds_ns_string)
        --rdds-test-prefix=STRING
		domain test prefix for RDDS monitoring (needed only if rdds servers specified)
        --only-cron
		only create cron jobs and exit
The default option is add new TLD. There is possible to --delete or --disable a part of TLD or all of TLD objects
	--epp
		Action with EPP
		(default: no)
	--dns
		Action with DNS
		(default: no)
	--rdds
		Action with RDDS
		(default: no)
        --help
                display this message
EOF
exit(1);
}

sub validate_input {
    my $msg = "";

    return if (defined($OPTS{'only-cron'}));

    $msg  = "TLD must be specified (--tld)\n" unless (defined($OPTS{'tld'}));
    $msg .= "type (--type) of TLD must be specified: @{[TLD_TYPE_G]}, @{[TLD_TYPE_CC]}, @{[TLD_TYPE_OTHER]} or @{[TLD_TYPE_TEST]}\n" if (!defined($OPTS{'delete'}) and !defined($OPTS{'disable'}) and (!defined($OPTS{'type'}) or
										($OPTS{'type'} ne TLD_TYPE_G and $OPTS{'type'} ne TLD_TYPE_CC and $OPTS{'type'} ne TLD_TYPE_OTHER and $OPTS{'type'} ne TLD_TYPE_TEST)));
    $msg .= "at least one IPv4 or IPv6 must be enabled (--ipv4 or --ipv6)\n" if (!defined($OPTS{'delete'}) and !defined($OPTS{'disable'}) and !defined($OPTS{'ipv4'}) and !defined($OPTS{'ipv6'}));
    $msg .= "DNS test prefix must be specified (--dns-test-prefix)\n" if (!defined($OPTS{'delete'}) and !defined($OPTS{'disable'}) and !defined($OPTS{'dns-test-prefix'}));
    $msg .= "RDDS test prefix must be specified (--rdds-test-prefix)\n" if ((defined($OPTS{'rdds43-servers'}) and !defined($OPTS{'rdds-test-prefix'})) or
									    (defined($OPTS{'rdds80-servers'}) and !defined($OPTS{'rdds-test-prefix'})));
    $msg .= "none or both --rdds43-servers and --rdds80-servers must be specified\n" if ((defined($OPTS{'rdds43-servers'}) and !defined($OPTS{'rdds80-servers'})) or
											 (defined($OPTS{'rdds80-servers'}) and !defined($OPTS{'rdds43-servers'})));

    if ($OPTS{'epp-servers'}) {
	$msg .= "EPP user must be specified (--epp-user)\n" unless ($OPTS{'epp-user'});
	$msg .= "EPP Client certificate file must be specified (--epp-cert)\n" unless ($OPTS{'epp-cert'});
	$msg .= "EPP Client private key file must be specified (--epp-privkey)\n" unless ($OPTS{'epp-privkey'});
	$msg .= "EPP server ID must be specified (--epp-serverid)\n" unless ($OPTS{'epp-serverid'});
	$msg .= "EPP domain test prefix must be specified (--epp-test-prefix)\n" unless ($OPTS{'epp-serverid'});
	$msg .= "EPP Server certificate file must be specified (--epp-servercert)\n" unless ($OPTS{'epp-servercert'});
    }

    $OPTS{'dns'} = 0 unless defined $OPTS{'dns'};
    $OPTS{'rdds'} = 0 unless defined $OPTS{'rdds'};
    $OPTS{'epp'} = 0 unless defined $OPTS{'epp'};

    unless ($msg eq "") {
	print($msg);
	usage();
    }
}

sub lc_options {
    foreach my $key (keys(%OPTS))
    {
	foreach ("tld", "rdds43-servers", "rdds80-servers=s", "epp-servers", "ns-servers-v4", "ns-servers-v6")
	{
	    $OPTS{$_} = lc($OPTS{$_}) if ($key eq $_);
	}
    }
}

sub add_default_actions() {

}

sub create_global_macros() {
    create_macro('{$RSM.IP4.MIN.PROBE.ONLINE}', 2, undef);
    create_macro('{$RSM.IP6.MIN.PROBE.ONLINE}', 2, undef);

    create_macro('{$RSM.IP4.MIN.SERVERS}', 4, undef);
    create_macro('{$RSM.IP6.MIN.SERVERS}', 4, undef);
    create_macro('{$RSM.IP4.REPLY.MS}', 500, undef);
    create_macro('{$RSM.IP6.REPLY.MS}', 500, undef);

    create_macro('{$RSM.DNS.TCP.RTT.LOW}', 1500, undef);
    create_macro('{$RSM.DNS.TCP.RTT.HIGH}', 7500, undef);
    create_macro('{$RSM.DNS.UDP.RTT.LOW}', 500, undef);
    create_macro('{$RSM.DNS.UDP.RTT.HIGH}', 2500, undef);
    create_macro('{$RSM.DNS.UDP.DELAY}', 60, undef);
    create_macro('{$RSM.DNS.TCP.DELAY}', 60, undef);
    create_macro('{$RSM.DNS.UPDATE.TIME}', 3600, undef);
    create_macro('{$RSM.DNS.PROBE.ONLINE}', 2, undef);
    create_macro('{$RSM.DNS.AVAIL.MINNS}', 2, undef);
    create_macro('{$RSM.DNS.ROLLWEEK.SLA}', 60, undef);

    create_macro('{$RSM.RDDS.RTT.LOW}', 2000, undef);
    create_macro('{$RSM.RDDS.RTT.HIGH}', 10000, undef);
    create_macro('{$RSM.RDDS.DELAY}', 60, undef);
    create_macro('{$RSM.RDDS.UPDATE.TIME}', 3600, undef);
    create_macro('{$RSM.RDDS.PROBE.ONLINE}', 2, undef);
    create_macro('{$RSM.RDDS.ROLLWEEK.SLA}', 60, undef);
    create_macro('{$RSM.RDDS.MAXREDIRS}', 10, undef);

    create_macro('{$RSM.EPP.DELAY}', 60, undef);
    create_macro('{$RSM.EPP.LOGIN.RTT.LOW}', 4000, undef);
    create_macro('{$RSM.EPP.LOGIN.RTT.HIGH}', 20000, undef);
    create_macro('{$RSM.EPP.UPDATE.RTT.LOW}', 4000, undef);
    create_macro('{$RSM.EPP.UPDATE.RTT.HIGH}', 20000, undef);
    create_macro('{$RSM.EPP.INFO.RTT.LOW}', 2000, undef);
    create_macro('{$RSM.EPP.INFO.RTT.HIGH}', 10000, undef);
    create_macro('{$RSM.EPP.PROBE.ONLINE}', 2, undef);
    create_macro('{$RSM.EPP.ROLLWEEK.SLA}', 60, undef);

    create_macro('{$RSM.PROBE.ONLINE.DELAY}', 60, undef);

    create_macro('{$RSM.TRIG.DOWNCOUNT}', '#1', undef);
    create_macro('{$RSM.TRIG.UPCOUNT}', '#3', undef);

    create_macro('{$RSM.INCIDENT.DNS.FAIL}', 3, undef);
    create_macro('{$RSM.INCIDENT.DNS.RECOVER}', 3, undef);
    create_macro('{$RSM.INCIDENT.DNSSEC.FAIL}', 3, undef);
    create_macro('{$RSM.INCIDENT.DNSSEC.RECOVER}', 3, undef);
    create_macro('{$RSM.INCIDENT.RDDS.FAIL}', 2, undef);
    create_macro('{$RSM.INCIDENT.RDDS.RECOVER}', 2, undef);
    create_macro('{$RSM.INCIDENT.EPP.FAIL}', 2, undef);
    create_macro('{$RSM.INCIDENT.EPP.RECOVER}', 2, undef);

    create_macro('{$RSM.SLV.DNS.UDP.RTT}', 99, undef);
    create_macro('{$RSM.SLV.DNS.TCP.RTT}', 99, undef);
    create_macro('{$RSM.SLV.NS.AVAIL}', 99, undef);
    create_macro('{$RSM.SLV.RDDS43.RTT}', 99, undef);
    create_macro('{$RSM.SLV.RDDS80.RTT}', 99, undef);
    create_macro('{$RSM.SLV.RDDS.UPD}', 99, undef);
    create_macro('{$RSM.SLV.DNS.NS.UPD}', 99, undef);
    create_macro('{$RSM.SLV.EPP.LOGIN}', 99, undef);
    create_macro('{$RSM.SLV.EPP.UPDATE}', 99, undef);
    create_macro('{$RSM.SLV.EPP.INFO}', 99, undef);

    create_macro('{$RSM.ROLLWEEK.THRESHOLDS}', RSM_ROLLWEEK_THRESHOLDS, undef);
    create_macro('{$RSM.ROLLWEEK.SECONDS}', 7200, undef);
    create_macro('{$RSM.PROBE.AVAIL.LIMIT}', '60', undef); # For finding unreachable probes. Probes are considered unreachable if last access time is over this limit of seconds.
}

sub create_tld_host($$$$) {
    my $tld_name = shift;
    my $tld_groupid = shift;
    my $tlds_groupid = shift;
    my $tld_type_groupid = shift;

    my $tld_hostid = create_host({'groups' => [{'groupid' => $tld_groupid}, {'groupid' => $tlds_groupid}, {'groupid' => $tld_type_groupid}],
                              'host' => $tld_name,
                              'interfaces' => [{'type' => INTERFACE_TYPE_AGENT, 'main' => true, 'useip' => true, 'ip'=> '127.0.0.1', 'dns' => '', 'port' => '10050'}]});

    pfail $tld_hostid->{'data'} if check_api_error($tld_hostid) eq true;

    create_slv_items($ns_servers, $tld_hostid, $tld_name);

    return $tld_hostid;
}

sub create_probe_health_tmpl() {
    my $templateid = create_template('Template Proxy Health', LINUX_TEMPLATEID);

    my $item_key = 'zabbix[proxy,{$RSM.PROXY_NAME},lastaccess]';

    my $options = {'name' => 'Availability of $2 Probe',
                                          'key_'=> $item_key,
                                          'hostid' => $templateid,
                                          'applications' => [get_application_id('Probe Availability', $templateid)],
                                          'type' => 5, 'value_type' => 3,
                                          'units' => 'unixtime', delay => '60'};

    create_item($options);

    $options = { 'description' => 'PROBE {HOST.NAME}: Probe {$RSM.PROXY_NAME} is not available',
                     'expression' => '{Template Proxy Health:'.$item_key.'.fuzzytime(2m)}=0',
                    'priority' => '4',
            };

    create_trigger($options);

    return $templateid;
}

sub manage_tld_objects($$$$$) {
    my $action = shift;
    my $tld = shift;
    my $dns = shift;
    my $epp = shift;
    my $rdds = shift;

    my $types = {'dns' => $dns, 'epp' => $epp, 'rdds' => $rdds};

    my $main_temlateid;

    my @tld_hostids;

    print "Trying to $action '$tld' TLD\n";

    print "Getting main host of the TLD: ";
    my $main_hostid = get_host($tld, false);

    if (scalar(%{$main_hostid})) {
        $main_hostid = $main_hostid->{'hostid'};
	print "success\n";
    }
    else {
        print "Could not find '$tld' host\n";
        exit;
    }

    print "Getting main template of the TLD: ";
    my $tld_template = get_template('Template '.$tld, false, true);

    if (scalar(%{$tld_template})) {
        $main_templateid = $tld_template->{'templateid'};
	print "success\n";
    }
    else {
        print "Could not find 'Template .$tld' template\n";
        exit;
    }

    foreach my $host (@{$tld_template->{'hosts'}}) {
	push @tld_hostids, $host->{'hostid'};
    }


    if ($dns eq true and $epp eq true and $rdds eq true) {
	print "You have choosed all possible options. Trying to $action TLD.\n";

	my @tmp_hostids;
	my @hostids_arr;

	push @tmp_hostids, {'hostid' => $main_hostid};

	foreach my $hostid (@tld_hostids) {
                push @tmp_hostids, {'hostid' => $hostid};
		push @hostids_arr, $hostid;
        }

	if ($action eq 'disable') {
	    my $result = disable_hosts(\@tmp_hostids);

	    if (scalar(%{$result})) {
		compare_arrays(\@hostids_arr, \@{$result->{'hostids'}});
	    }
	    else {
		print "An error happened while removing hosts!\n";
	    }

	    exit;
	}

	if ($action eq 'delete') {
	    remove_hosts( \@tmp_hostids );
	    remove_templates([ $main_templateid ]);

	    my $hostgroupid = get_host_group('TLD '.$tld, false);
	    $hostgroupid = $hostgroupid->{'groupid'};
	    remove_hostgroups( [ $hostgroupid ] );
	    return;
	}
    }

    foreach my $type (keys %{$types}) {
	next if $types->{$type} eq false;

	my @itemids;

	my $template_items = get_items_like($main_templateid, $type, true);
	my $host_items = get_items_like($main_hostid, $type, false);

	if (scalar(%{$template_items})) {
	    foreach my $itemid (%{$template_items}) {
		push @itemids, $itemid;
	    }
	}
	else {
	    print "Could not find $type related items on the template level\n";
	}

	if (scalar(%{$host_items})) {
	    foreach my $itemid (%{$host_items}) {
		push @itemids, $itemid;
	    }
	}
	else {
	    print "Could not find $type related items on host level\n";
	}

	if ($action eq 'disable' and scalar(@itemids)) {
	    disable_items(\@itemids);
	}

	if ($action eq 'delete' and scalar(@itemids)) {
	    remove_items(\@itemids);
#	    remove_applications_by_items(\@itemids);
	}
    }
}

sub compare_arrays($$) {
    my $array_A = shift;
    my $array_B = shift;

    my @result;

    foreach my $a (@{$array_A}) {
	my $found = false;
	foreach $b (@${array_B}) {
	    $found = true if $a eq $b;
	}

	push @result, $a if $found eq false;
    }

    return @result;
}

sub check_api_error($) {
    my $str = shift;

    return true if 'HASH' eq ref($str) and (defined $str->{'error'} or defined $str->{'code'});

    return false;
}
