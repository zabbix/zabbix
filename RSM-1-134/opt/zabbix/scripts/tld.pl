#!/usr/bin/perl
#
# DNS test							rsm.dns				(simple check, Proxy)
#								rsm.dns.udp.rtt			(trapper, Proxy)
#					if EPP enabled		rsm.dns.udp.upd			-|-
#								rsm.dns.tcp.rtt			-|-
#					if EPP enabled		rsm.dns.tcp.upd			-|-
#
# RDDS test				if any RDDS enabled	rsm.rdds			(simple check, Proxy)
#					if RDDS43 enabled	rsm.rdds.43.ip			(trapper, Proxy)
#					if RDDS43 & EPP enabled	rsm.rdds.43.rtt			-|-
#					if EPP enabled		rsm.rdds.43.upd			-|-
#					if RDDS80 enabled	rsm.rdds.80.ip			-|-
#					if RDDS80 enabled	rsm.rdds.80.rtt			-|-
#   					if RDAP enabled		rsm.rdds.rdap.ip		-|-
#   					if RDAP enabled		rsm.rdds.rdap.rtt		-|-
#					if RDAP & EPP enabled	rsm.rdds.rdap.upd		-|-
#								rsm.rdds.rdap.ip		-|-
#								rsm.rdds.rdap.rtt		-|-
#								rsm.rdds.rdap.upd		-|-
#
# EPP test				if EPP enabled		rsm.epp				(simple check, Proxy)
#								rsm.epp.ip[{$RSM.TLD}]		(trapper, Proxy)
#								rsm.epp.rtt[{$RSM.TLD},login]	-|-
#								rsm.epp.rtt[{$RSM.TLD},update]	-|-
#								rsm.epp.rtt[{$RSM.TLD},info]	-|-
#
# DNS NS availability						rsm.slv.dns.ns.avail		(trapper, Server)
# DNS NS monthly availability					rsm.slv.dns.ns.month		-|-
# DNS monthly resolution RTT (UDP)				rsm.slv.dns.ns.rtt.udp.month	-|-
# DNS monthly resolution RTT (TCP)				rsm.slv.dns.ns.rtt.tcp.month	-|-
# DNS monthly update time					rsm.slv.dns.ns.upd.month	-|-
# DNS availability						rsm.slv.dns.avail		-|-
# DNS rolling week						rsm.slv.dns.rollweek		-|-
#
# DNSSEC proper resolution					rsm.slv.dnssec.avail		-|-
# DNSSEC rolling week						rsm.slv.dnssec.rollweek		-|-
#
# RDDS availability						rsm.slv.rdds.avail		-|-
# RDDS rolling week						rsm.slv.rdds.rollweek		-|-
# RDDS43 monthly resolution RTT					rsm.slv.rdds43.rtt		-|-
# RDDS80 monthly resolution RTT					rsm.slv.rdds80.rtt		-|-
# RDAP monthly resolution RTT					rsm.slv.rdap.rtt		-|-
# RDDS43 monthly update time					rsm.slv.rdds43.upd		-|-
# RDAP monthly update time					rsm.slv.rdap.upd		-|-
#
# EPP availability						rsm.slv.epp.avail		-|-
# EPP minutes of downtime					rsm.slv.epp.downtime		-|-
# EPP weekly unavailability					rsm.slv.epp.rollweek		-|-
# EPP monthly LOGIN resolution RTT				rsm.slv.epp.rtt.login		-|-
# EPP monthly UPDATE resolution RTT				rsm.slv.epp.rtt.update		-|-
# EPP monthly INFO resolution RTT				rsm.slv.epp.rtt.info		-|-

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
}
use lib $MYDIR;

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
sub update_epp_objects($);
sub update_epp_objects($);
sub get_nsservers_list($);
sub update_nsservers($$);
sub get_tld_list();
sub get_services($);
sub create_slv_monthly($$$$$);

my $trigger_rollweek_thresholds = rsm_trigger_rollweek_thresholds;

my $cfg_global_macros = cfg_global_macros;

my $rsm_rdds_interfaces = rsm_rdds_interfaces;

my ($rsm_groupid, $rsm_hostid);

my ($ns_servers, $root_servers_macros, $applications);

my ($main_templateid, $tld_groupid, $tld_type_groupid, $tlds_groupid, $tld_hostid, $probes_groupid, $probes_mon_groupid, $proxy_mon_templateid);

my %OPTS;
my $rv = GetOptions(\%OPTS,
		    "tld=s",
		    "only-tld!",
		    "delete!",
		    "disable!",
		    "type=s",
		    "set-type!",
		    "rdds43-servers=s",
		    "rdds80-servers=s",
		    "rdap-servers=s",
		    "dns-test-prefix=s",
		    "rdds-test-prefix=s",
		    "ipv4!",
		    "ipv6!",
		    "dns!",
		    "epp!",
		    "only-epp!",
		    "rdds!",
		    "dnssec!",
		    "epp-servers=s",
		    "epp-user=s",
		    "epp-cert=s",
		    "epp-privkey=s",
		    "epp-commands=s",
		    "epp-serverid=s",
		    "epp-test-prefix=s",
		    "dns-test-prefix-epp=s",
		    "epp-servercert=s",
		    "ns-servers-v4=s",
		    "ns-servers-v6=s",
		    "rdds-ns-string=s",
		    "get-nsservers-list!",
		    "update-nsservers!",
		    "list-services!",
		    "setup-cron:s",
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
if (defined($OPTS{'setup-cron'})) {
    create_cron_jobs($config->{'slv'}->{'path'});
    print("cron jobs created successfully\n");
    exit;
}

pfail("Zabbix API URL is not specified. Please check configuration file") unless defined $config->{'zapi'}->{'url'};
pfail("Username for Zabbix API is not specified. Please check configuration file") unless defined $config->{'zapi'}->{'user'};
pfail("Password for Zabbix API is not specified. Please check configuration file") unless defined $config->{'zapi'}->{'password'};

my $result = zbx_connect($config->{'zapi'}->{'url'}, $config->{'zapi'}->{'user'}, $config->{'zapi'}->{'password'});

if ($result ne true) {
    pfail("Could not connect to Zabbix API. ".$result->{'data'});
}

if (defined($OPTS{'set-type'})) {
    if (set_tld_type($OPTS{'tld'}, $OPTS{'type'}) == true)
    {
	print("${OPTS{'tld'}} set to \"${OPTS{'type'}}\"\n");
    }
    else
    {
	print("${OPTS{'tld'}} is already set to \"${OPTS{'type'}}\"\n");
    }
    exit;
}

#### Manage NS + IP server pairs ####
if (defined($OPTS{'get-nsservers-list'})) {
    my $nsservers;

    if (defined($OPTS{'tld'})) {
	$nsservers->{$OPTS{'tld'}} = get_nsservers_list($OPTS{'tld'});
    }
    else {
	my @tlds = get_tld_list();

	foreach my $tld (@tlds) {
	    my $ns = get_nsservers_list($tld);

	    $nsservers->{$tld} = $ns;
	}
    }

    foreach my $tld (sort keys %{$nsservers}) {
	my $ns = $nsservers->{$tld};
	foreach my $type (sort keys %{$ns}) {
	    foreach my $ns_name (sort keys %{$ns->{$type}}) {
		my @ip_list = @{$ns->{$type}->{$ns_name}};
		foreach my $ip (@ip_list) {
	    	    print $tld.",".$type.",".$ns_name.",".$ip."\n";
		}
	    }
	}
    }
    exit;
}

if (defined($OPTS{'list-services'})) {
    my @tlds = get_tld_list();

    my $report;

	my @columns = ('tld_type', '{$RSM.DNS.TESTPREFIX}', '{$RSM.RDDS.NS.STRING}', '{$RSM.RDDS.TESTPREFIX}',
			'{$RSM.TLD.DNSSEC.ENABLED}', '{$RSM.TLD.EPP.ENABLED}', '{$RSM.TLD.RDDS43.ENABLED}',
			'{$RSM.TLD.RDDS80.ENABLED}', '{$RSM.TLD.RDAP.ENABLED}');

    foreach my $tld (@tlds) {
	my $services = get_services($tld);

        $report->{$tld} = $services;
    }

    foreach my $tld (sort keys %{$report}) {
	print $tld.",";

	my $count = 0;

	foreach my $column (@columns) {
	    if (defined($report->{$tld}->{$column})) {
		print $report->{$tld}->{$column};
	    }

	    $count++;

	    print "," if (scalar(@columns) != $count);
	}

	print "\n";
    }

    exit;
}

if (defined($OPTS{'update-nsservers'})) {
    # Possible use dig instead of --ns-servers-v4 and ns-servers-v6
    $ns_servers = get_ns_servers($OPTS{'tld'});
    update_nsservers($OPTS{'tld'}, $ns_servers);
    exit;
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

## Creating all global macros##
## Please check the function to change default values of macros ##
unless ($OPTS{'only-tld'} eq true) {
    create_global_macros();
}

## Geting some global macros related to item refresh interval ##
## Values are used as item update interval ##
my @glb_macro_list = keys %{$cfg_global_macros};
my $glb_macros = get_global_macros(\@glb_macro_list);

foreach my $macro (keys %{$cfg_global_macros}) {
    if (exists($glb_macros->{$macro})) {
	$cfg_global_macros->{$macro} = $glb_macros->{$macro}->{'value'};
    }
    else {
	pfail('cannot get global macro ', $macro);
    }
}

#### Update only EPP params ####
if (defined($OPTS{'only-epp'})) {
	update_epp_objects($OPTS{'tld'});
	exit;
}

#### Adding new TLD ####
my $proxies = get_proxies_list($OPTS{'only-tld'});

pfail("Cannot find existing proxies") if (scalar(keys %{$proxies}) == 0);

# RSM host is required to have history of global configuration changes #
# There are monitored changes of global macros #

unless ($OPTS{'only-tld'} eq true) {
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
}

$ns_servers = get_ns_servers($OPTS{'tld'});

pfail("Could not retrive NS servers for '".$OPTS{'tld'}."' TLD") unless (scalar(keys %{$ns_servers}));

$root_servers_macros = update_root_servers($OPTS{'only-tld'});

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

unless ($OPTS{'only-tld'} eq true) {
    $probes_groupid = create_group('Probes');

    pfail $probes_groupid->{'data'} if check_api_error($probes_groupid) eq true;

    $probes_mon_groupid = create_group('Probes - Mon');

    pfail $probes_mon_groupid->{'data'} if check_api_error($probes_mon_groupid) eq true;

    $proxy_mon_templateid = create_probe_health_tmpl();
}

## Creating TLD hosts for each probe ##

foreach my $proxyid (sort keys %{$proxies}) {
    my $probe_name = $proxies->{$proxyid}->{'host'};

    my $status = HOST_STATUS_MONITORED;

    print $proxyid."\n";
    print $proxies->{$proxyid}->{'host'}."\n";

    my $probe_status = $proxies->{$proxyid}->{'status'};

    if ($probe_status == HOST_STATUS_PROXY_ACTIVE) {
	$status = HOST_STATUS_NOT_MONITORED;
    }

    my $proxy_groupid = create_group($probe_name);

    my $probe_templateid;

    if ($probe_status == HOST_STATUS_PROXY_ACTIVE) {
	$probe_templateid = create_probe_template($probe_name, 0, 0, 0, 0, 0, 0);
    }
    else {
	$probe_templateid = create_probe_template($probe_name);
    }


    unless ($OPTS{'only-tld'} eq true) {
	my $probe_status_templateid = create_probe_status_template($probe_name, $probe_templateid, $root_servers_macros);

        create_host({'groups' => [{'groupid' => $proxy_groupid}, {'groupid' => $probes_groupid}],
                                          'templates' => [{'templateid' => $probe_status_templateid}],
                                          'host' => $probe_name,
                                          'status' => $status,
                                          'proxy_hostid' => $proxyid,
                                          'interfaces' => [{'type' => 1, 'main' => true, 'useip' => true,
							    'ip'=> '127.0.0.1',
							    'dns' => '', 'port' => '10050'}]
		});

	my $hostid = create_host({'groups' => [{'groupid' => $probes_mon_groupid}],
                                          'templates' => [{'templateid' => $proxy_mon_templateid}],
                                          'host' => $probe_name.' - mon',
                                          'status' => $status,
                                          'interfaces' => [{'type' => 1, 'main' => true, 'useip' => true,
                                                            'ip'=> $proxies->{$proxyid}->{'interfaces'}[0]->{'ip'},
                                                            'dns' => '', 'port' => '10050'}]
            		    });

	create_macro('{$RSM.PROXY_NAME}', $probe_name, $hostid, 1);
    }

    create_host({'groups' => [{'groupid' => $tld_groupid}, {'groupid' => $proxy_groupid}],
                                          'templates' => [{'templateid' => $main_templateid}, {'templateid' => $probe_templateid}],
                                          'host' => $OPTS{'tld'}.' '.$probe_name,
                                          'status' => $status,
                                          'proxy_hostid' => $proxyid,
                                          'interfaces' => [{'type' => 1, 'main' => true, 'useip' => true, 'ip'=> '127.0.0.1', 'dns' => '', 'port' => '10050'}]});
}

unless ($OPTS{'only-tld'} eq true) {
    create_probe_status_host($probes_mon_groupid);
}

exit;

########### FUNCTIONS ###############

sub get_ns_servers {
    my $tld = shift;

    if ($OPTS{'ns-servers-v4'} or $OPTS{'ns-servers-v6'}) {
	if ($OPTS{'ns-servers-v4'} and ($OPTS{'ipv4'} == 1 or $OPTS{'update-nsservers'})) {
	    my @nsservers = split(/\s/, $OPTS{'ns-servers-v4'});
	    foreach my $ns (@nsservers) {
		next if ($ns eq '');

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

	if ($OPTS{'ns-servers-v6'} and ($OPTS{'ipv6'} == 1 or $OPTS{'update-nsservers'})) {
	    my @nsservers = split(/\s/, $OPTS{'ns-servers-v6'});
	    foreach my $ns (@nsservers) {
		next if ($ns eq '');

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
    my $is_new = shift;

    $is_new = false unless defined $is_new;

    pfail("undefined template ID passed to create_item_dns_rtt()") unless ($templateid);
    pfail("no protocol parameter specified to create_item_dns_rtt()") unless ($proto);

    my $proto_lc = lc($proto);
    my $proto_uc = uc($proto);

    my $item_key = 'rsm.dns.'.$proto_lc.'.rtt[{$RSM.TLD},'.$ns_name.','.$ip.']';

    unless (exists($applications->{$templateid}->{'DNS ('.$proto_uc.')'})) {
	$applications->{$templateid}->{'DNS ('.$proto_uc.')'} = get_application_id('DNS ('.$proto_uc.')', $templateid, $is_new);
    }

    my $options = {'name' => 'DNS RTT of $2 ($3) ('.$proto_uc.')',
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [$applications->{$templateid}->{'DNS ('.$proto_uc.')'}],
                                              'type' => 2, 'value_type' => 0,
					      'status' => ITEM_STATUS_ACTIVE,
                                              'valuemapid' => rsm_value_mappings->{'dns_test'}};

    create_item($options, $is_new);
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
					    'status' => ITEM_STATUS_ACTIVE,
					      'valuemapid' => rsm_value_mappings->{'rsm_avail'}};
    }
    elsif ($value_type == VALUE_TYPE_NUM)
    {
	$options = {'name' => $name,
                                              'key_'=> $key,
                                              'hostid' => $hostid,
                                              'type' => 2, 'value_type' => 3,
					    'status' => ITEM_STATUS_ACTIVE,
					      'applications' => $applicationids};
    }
    elsif ($value_type == VALUE_TYPE_PERC) {
	$options = {'name' => $name,
                                              'key_'=> $key,
                                              'hostid' => $hostid,
                                              'type' => 2, 'value_type' => 0,
                                              'applications' => $applicationids,
					    'status' => ITEM_STATUS_ACTIVE,
					      'units' => '%'};
    }
    elsif ($value_type == VALUE_TYPE_DOUBLE) {
	$options = {'name' => $name,
                                              'key_'=> $key,
                                              'hostid' => $hostid,
                                              'type' => 2, 'value_type' => 0,
                                              'applications' => $applicationids,
					    'status' => ITEM_STATUS_ACTIVE};
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
    my $ipv = shift;
    my $is_new = shift;

    $is_new = false unless defined $is_new;

    foreach my $proto_uc ('UDP', 'TCP')
    {
	    my $proto_lc = lc($proto_uc);

	    unless (exists($applications->{$templateid}->{'DNS ('.$proto_uc.')'})) {
		    $applications->{$templateid}->{'DNS ('.$proto_uc.')'} = get_application_id('DNS ('.$proto_uc.')', $templateid, $is_new);
	    }

	    my $options = {'name' => 'DNS update time of $2 ($3) ('.$proto_uc.')',
                                              'key_'=> 'rsm.dns.'.$proto_lc.'.upd[{$RSM.TLD},'.$ns_name.','.$ip.']',
                                              'hostid' => $templateid,
                                              'applications' => [$applications->{$templateid}->{'DNS ('.$proto_uc.')'}],
                                              'type' => 2, 'value_type' => 0,
                                              'valuemapid' => rsm_value_mappings->{'dns_test'},
		                              'status' => (defined($OPTS{'epp-servers'}) ? 0 : 1)};

	    create_item($options, $is_new);
    }
}

sub create_items_dns {
    my $templateid = shift;
    my $template_name = shift;
    my $is_new = shift;

    $is_new = false unless defined $is_new;

    my $item_key = 'rsm.dns[{$RSM.TLD}]';

    unless (exists($applications->{$templateid}->{'DNS'})) {
        $applications->{$templateid}->{'DNS'} = get_application_id('DNS', $templateid, $is_new)
    }

    my $options = {'name' => "DNS test",
                                              'key_'=> $item_key,
                                              'hostid' => $templateid,
                                              'applications' => [$applications->{$templateid}->{'DNS'}],
                                              'type' => 3, 'value_type' => 3,
                                              'valuemapid' => rsm_value_mappings->{'dns_test_result'},
                                              'delay' => $cfg_global_macros->{'{$RSM.DNS.DELAY}'}};

    create_item($options, $is_new);
}

sub create_items_rdds
{
	my $templateid = shift;
	my $template_name = shift;
	my $is_new = shift;

	$is_new = false unless defined $is_new;

	my $any_interface_enabled = false;
	my %hosts;
	my $options;

	$options->{'hostid'} = $templateid;
	$options->{'type'} = 2;

	while (my ($interface, $details) = each(%{$rsm_rdds_interfaces}))
	{
		if (defined($OPTS{$details->{'option'}}))
		{
			$any_interface_enabled ||= true;
			$hosts{$interface} = $OPTS{$details->{'option'}};

			unless (exists($applications->{$templateid}->{$interface}))
			{
				$applications->{$templateid}->{$interface} =
						get_application_id($interface, $templateid, $is_new);
			}

			$options->{'applications'} = [$applications->{$templateid}->{$interface}];

			$options->{'name'} = $interface . ' IP';
			$options->{'key_'} = 'rsm.rdds.' . $details->{'keypart'} . '.ip[{$RSM.TLD}]';
			$options->{'value_type'} = 1;
			delete($options->{'valuemapid'}) if (exists($options->{'valuemapid'}));
			create_item($options, $is_new);

			$options->{'name'} = $interface . ' RTT';
			$options->{'key_'} = 'rsm.rdds.' . $details->{'keypart'} . '.rtt[{$RSM.TLD}]';
			$options->{'value_type'} = 0;
			$options->{'valuemapid'} = rsm_value_mappings->{'rsm_rdds_result'};
			create_item($options, $is_new);

			if ($details->{'update'} && defined($OPTS{'epp-servers'}))
			{
				$options->{'name'} = $interface . ' update time';
				$options->{'key_'} = 'rsm.rdds.' . $details->{'keypart'} . '.upd[{$RSM.TLD}]';
				$options->{'value_type'} = 0;
				$options->{'valuemapid'} = rsm_value_mappings->{'rsm_rdds_result'};
				create_item($options, $is_new);
			}
		}
	}

	if ($any_interface_enabled)
	{
		unless (exists($applications->{$templateid}->{'RDDS'}))
		{
			$applications->{$templateid}->{'RDDS'} = get_application_id('RDDS', $templateid, $is_new);
		}

		$options->{'applications'} = [$applications->{$templateid}->{'RDDS'}];

		$options->{'name'} = 'RDDS test';
		$options->{'key_'} = 'rsm.rdds[{$RSM.TLD},"' .
				(exists($hosts{'RDDS43'}) ? $hosts{'RDDS43'} : '') . '","' .
				(exists($hosts{'RDDS80'}) ? $hosts{'RDDS80'} : '') . '","' .
				(exists($hosts{'RDAP'}) ? $hosts{'RDAP'} : '') . '"]';
		$options->{'type'} = 3;
		$options->{'value_type'} = 3;
		$options->{'valuemapid'} = rsm_value_mappings->{'rsm_rdds_probe_result'};
		$options->{'delay'} = $cfg_global_macros->{'{$RSM.RDDS.DELAY}'};
		create_item($options, $is_new);
	}
}

sub create_items_epp {
    my $templateid = shift;
    my $template_name = shift;
    my $is_new = shift;

    $is_new = false unless defined $is_new;

    unless (exists($applications->{$templateid}->{'EPP'})) {
        $applications->{$templateid}->{'EPP'} = get_application_id('EPP', $templateid, $is_new)
    }

    my $applicationid = $applications->{$templateid}->{'EPP'};

    my ($item_key, $options);

    $item_key = 'rsm.epp[{$RSM.TLD},"{$RSM.EPP.SERVERS}"]';

    $options = {'name' => 'EPP test',
		'key_'=> $item_key,
		'hostid' => $templateid,
		'applications' => [$applicationid],
		'type' => 3, 'value_type' => 3,
		'valuemapid' => rsm_value_mappings->{'epp_test_result'},
		'delay' => $cfg_global_macros->{'{$RSM.EPP.DELAY}'}};

    create_item($options, $is_new);

    $item_key = 'rsm.epp.ip[{$RSM.TLD}]';

    $options = {'name' => 'EPP IP',
		'key_'=> $item_key,
		'hostid' => $templateid,
		'applications' => [$applicationid],
		'type' => 2, 'value_type' => 1};

    create_item($options, $is_new);

    $item_key = 'rsm.epp.rtt[{$RSM.TLD},login]';

    $options = {'name' => 'EPP $2 command RTT',
		'key_'=> $item_key,
		'hostid' => $templateid,
		'applications' => [$applicationid],
		'type' => 2, 'value_type' => 0,
		'valuemapid' => rsm_value_mappings->{'epp_test'}};

    create_item($options, $is_new);

    $item_key = 'rsm.epp.rtt[{$RSM.TLD},update]';

    $options = {'name' => 'EPP $2 command RTT',
		'key_'=> $item_key,
		'hostid' => $templateid,
		'applications' => [$applicationid],
		'type' => 2, 'value_type' => 0,
		'valuemapid' => rsm_value_mappings->{'epp_test'}};

    create_item($options, $is_new);

    $item_key = 'rsm.epp.rtt[{$RSM.TLD},info]';

    $options = {'name' => 'EPP $2 command RTT',
		'key_'=> $item_key,
		'hostid' => $templateid,
		'applications' => [$applicationid],
		'type' => 2, 'value_type' => 0,
		'valuemapid' => rsm_value_mappings->{'epp_test'}};

    create_item($options, $is_new);
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

    my $template_data = create_template($template_name);
    my $templateid = $template_data->{'templateid'};
    my $is_new = $template_data->{'is_new'};

    pfail("Could not create main template for '".$tld."' TLD. ".$templateid->{'data'}) if check_api_error($templateid) eq true;

    unless (exists($applications->{$templateid}->{'Configuration'})) {
        $applications->{$templateid}->{'Configuration'} = get_application_id('Configuration', $templateid, $is_new)
    }

    my $delay = 300;
    my $appid = $applications->{$templateid}->{'Configuration'};
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

        my $itemid = create_item($options, $is_new);

	print $itemid->{'data'}."\n" if check_api_error($itemid) eq true;
    }

    foreach my $ns_name (sort keys %{$ns_servers}) {
	print $ns_name."\n";

	foreach my $ipv (keys %{$ns_servers->{$ns_name}}) {
	    my $ip_list = $ns_servers->{$ns_name}->{$ipv};

	    $ipv =~s/v([46])/$1/;
	    foreach my $ip (@{$ip_list}) {
		print "        --v$ipv     $ip\n";

		create_item_dns_rtt($ns_name, $ip, $templateid, $template_name, "tcp", $ipv, $is_new);
		create_item_dns_rtt($ns_name, $ip, $templateid, $template_name, "udp", $ipv, $is_new);
		create_item_dns_udp_upd($ns_name, $ip, $templateid, $template_name, $ipv, $is_new) if (defined($OPTS{'epp-servers'}));
	    }
        }
    }

    create_items_dns($templateid, $template_name, $is_new);
    create_items_rdds($templateid, $template_name, $is_new);
    create_epp_objects($templateid, $template_name, $tld, $is_new) if (defined($OPTS{'epp-servers'}));

    create_macro('{$RSM.TLD}', $tld, $templateid, undef, $is_new);
    create_macro('{$RSM.DNS.TESTPREFIX}', $OPTS{'dns-test-prefix'}, $templateid, undef, $is_new);
    create_macro('{$RSM.RDDS.TESTPREFIX}', $OPTS{'rdds-test-prefix'}, $templateid, undef, $is_new) if (defined($OPTS{'rdds-test-prefix'}));
    create_macro('{$RSM.RDDS.NS.STRING}', defined($OPTS{'rdds-ns-string'}) ? $OPTS{'rdds-ns-string'} : cfg_default_rdds_ns_string, $templateid, undef, $is_new);
    create_macro('{$RSM.TLD.DNSSEC.ENABLED}', defined($OPTS{'dnssec'}) ? 1 : 0, $templateid, true, $is_new);
    create_macro('{$RSM.TLD.EPP.ENABLED}', defined($OPTS{'epp-servers'}) ? 1 : 0, $templateid, true, $is_new);

	while (my ($interface, $details) = each(%{$rsm_rdds_interfaces}))
	{
		create_macro('{$RSM.TLD.' . $interface . '.ENABLED}', (defined($OPTS{$details->{'option'}}) ? 1 : 0),
				$templateid, true, $is_new);
	}

    return $templateid;
}

sub create_epp_objects($$$$) {
    my $templateid = shift;
    my $template_name = shift;
    my $tld = shift;
    my $is_new = shift;

    create_items_epp($templateid, $template_name, $is_new);

    my $m = '{$RSM.EPP.KEYSALT}';
    my $keysalt = get_global_macro_value($m);
    pfail('cannot get macro ', $m) unless defined($keysalt);
    trim($keysalt);
    pfail("the value of global macro $m is empty") unless ($keysalt);
    pfail("the value of global macro $m must contain | ($keysalt)") unless ($keysalt =~ m/\|/);

    if ($OPTS{'epp-commands'}) {
	create_macro('{$RSM.EPP.COMMANDS}', $OPTS{'epp-commands'}, $templateid, true);
    } else {
	create_macro('{$RSM.EPP.COMMANDS}', '/opt/test-sla/epp-commands/'.$tld, $templateid);
    }

    create_macro('{$RSM.EPP.USER}', $OPTS{'epp-user'}, $templateid, true, $is_new);
    create_macro('{$RSM.EPP.CERT}', encode_base64(read_file($OPTS{'epp-cert'}), ''),  $templateid, true, $is_new);
    create_macro('{$RSM.EPP.SERVERID}', $OPTS{'epp-serverid'}, $templateid, true, $is_new);
    create_macro('{$RSM.EPP.TESTPREFIX}', $OPTS{'epp-test-prefix'}, $templateid, true, $is_new);
    create_macro('{$RSM.EPP.SERVERCERTMD5}', get_md5($OPTS{'epp-servercert'}), $templateid, true, $is_new);
    create_macro('{$RSM.EPP.SERVERS}', $OPTS{'epp-servers'}, $templateid, true, $is_new);

    create_macro('{$RSM.DNS.TESTPREFIX.EPP}', $OPTS{'dns-test-prefix-epp'}, $templateid, true, $is_new);

    my $passphrase = get_sensdata("Enter EPP secret key passphrase: ");
    my $passwd = get_sensdata("Enter EPP password: ");
    create_macro('{$RSM.EPP.PASSWD}', get_encrypted_passwd($keysalt, $passphrase, $passwd), $templateid, true, $is_new);
    $passwd = undef;
    create_macro('{$RSM.EPP.PRIVKEY}', get_encrypted_privkey($keysalt, $passphrase, $OPTS{'epp-privkey'}), $templateid, true, $is_new);
    $passphrase = undef;

    print("EPP data saved successfully.\n");
}

sub create_all_slv_ns_items {
    my $ns_name = shift;
    my $ip = shift;
    my $host_name = shift;
    my $hostid = shift;

    unless (exists($applications->{$hostid}->{APP_SLV_MONTHLY})) {
        $applications->{$hostid}->{APP_SLV_MONTHLY} = get_application_id(APP_SLV_MONTHLY, $hostid)
    }
    unless (exists($applications->{$hostid}->{APP_SLV_PARTTEST})) {
        $applications->{$hostid}->{APP_SLV_PARTTEST} = get_application_id(APP_SLV_PARTTEST, $hostid)
    }

    my $slv_dns_ns_avail = create_slv_item('DNS NS availability: $1 ($2)', 'rsm.slv.dns.ns.avail['.$ns_name.','.$ip.']', $hostid, VALUE_TYPE_AVAIL, [$applications->{$hostid}->{APP_SLV_PARTTEST}]);
    my $slv_dns_ns_downtime = create_slv_item('DNS NS minutes of downtime: $1 ($2)', 'rsm.slv.dns.ns.downtime['.$ns_name.','.$ip.']', $hostid, VALUE_TYPE_NUM, [$applications->{$hostid}->{APP_SLV_MONTHLY}]);

    create_graph('DNS NS Availability - ['.$ns_name.', '.$ip.'] Accumulated minutes of downtime', [{'itemid' => $slv_dns_ns_downtime, 'hostid' => $hostid}]);
    create_graph('DNS NS Availability - ['.$ns_name.', '.$ip.'] UP/DOWN', [{'itemid' => $slv_dns_ns_avail, 'hostid' => $hostid}]);

    my $options = {
	    'description' => 'Name Server '.$ns_name.' ('.$ip.') has been down for over {$RSM.SLV.NS.AVAIL} minutes',
	    'expression' => '{'.$host_name.':rsm.slv.dns.ns.downtime['.$ns_name.','.$ip.'].last(0)}>{$RSM.SLV.NS.AVAIL}',
	    'priority' => '4'
    };

    create_trigger($options);

    return $slv_dns_ns_downtime;
}

sub create_slv_ns_items {
    my $ns_servers = shift;
    my $host_name = shift;
    my $hostid = shift;

    my @slv_dns_ns_downtime_itemids;

    foreach my $ns_name (sort keys %{$ns_servers}) {
	foreach my $ipv (keys %{$ns_servers->{$ns_name}}) {
	    my $ip_list = $ns_servers->{$ns_name}->{$ipv};

	    foreach my $ip (@{$ip_list}) {
		my $slv_dns_ns_downtime = create_all_slv_ns_items($ns_name, $ip, $host_name, $hostid);

		push(@slv_dns_ns_downtime_itemids, $slv_dns_ns_downtime);
	    }
	}
    }

    if (scalar(@slv_dns_ns_downtime_itemids) != 0)
    {
	create_graph('DNS NS Availability - Accumulated minutes of downtime',
	    [map {{'itemid' => $_, 'hostid' => $hostid}} (@slv_dns_ns_downtime_itemids)]);
    }
}

sub create_slv_items {
    my $ns_servers = shift;
    my $hostid = shift;
    my $host_name = shift;

    my $options;

    create_slv_ns_items($ns_servers, $host_name, $hostid);

    unless (exists($applications->{$hostid}->{APP_SLV_PARTTEST})) {
        $applications->{$hostid}->{APP_SLV_PARTTEST} = get_application_id(APP_SLV_PARTTEST, $hostid)
    }
    unless (exists($applications->{$hostid}->{APP_SLV_MONTHLY})) {
        $applications->{$hostid}->{APP_SLV_MONTHLY} = get_application_id(APP_SLV_MONTHLY, $hostid)
    }
    unless (exists($applications->{$hostid}->{APP_SLV_ROLLWEEK})) {
        $applications->{$hostid}->{APP_SLV_ROLLWEEK} = get_application_id(APP_SLV_ROLLWEEK, $hostid)
    }

    create_slv_item('DNS availability', 'rsm.slv.dns.avail', $hostid, VALUE_TYPE_AVAIL, [$applications->{$hostid}->{APP_SLV_PARTTEST}]);

    my $slv_dns_downtime = create_slv_item('DNS minutes of downtime', 'rsm.slv.dns.downtime', $hostid, VALUE_TYPE_NUM,
        [$applications->{$hostid}->{APP_SLV_MONTHLY}]);
    create_graph('DNS Service Availability - Accumulated minutes of downtime', [{ 'itemid' => $slv_dns_downtime, 'hostid' => $hostid }]);

    my $item_key = 'rsm.slv.dns.downtime';

    $options = {
	'description' => 'DNS has been down for over {$RSM.SLV.DNS.AVAIL} minutes',
	'expression' => '{'.$host_name.':'.$item_key.'.last(0)}>{$RSM.SLV.DNS.AVAIL}',
	'priority' => '4'
    };

    create_trigger($options);

    create_slv_monthly("DNS UDP Resolution RTT", "rsm.slv.dns.udp.rtt", $hostid, $host_name, '{$RSM.SLV.DNS.UDP.RTT}');
    create_slv_monthly("DNS TCP Resolution RTT", "rsm.slv.dns.tcp.rtt", $hostid, $host_name, '{$RSM.SLV.DNS.TCP.RTT}');

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

    create_slv_item('DNS weekly unavailability', 'rsm.slv.dns.rollweek', $hostid, VALUE_TYPE_PERC, [$applications->{$hostid}->{APP_SLV_ROLLWEEK}]);

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
	create_slv_item('DNSSEC availability', 'rsm.slv.dnssec.avail', $hostid, VALUE_TYPE_AVAIL, [$applications->{$hostid}->{APP_SLV_PARTTEST}]);

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

	create_slv_item('DNSSEC weekly unavailability', 'rsm.slv.dnssec.rollweek', $hostid, VALUE_TYPE_PERC, [$applications->{$hostid}->{APP_SLV_ROLLWEEK}]);

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

	if (defined($OPTS{'rdds43-servers'}) || defined($OPTS{'rdds80-servers'}) || defined($OPTS{'rdap-servers'}))
	{
		create_slv_item('RDDS availability', 'rsm.slv.rdds.avail', $hostid, VALUE_TYPE_AVAIL,
				[$applications->{$hostid}->{APP_SLV_PARTTEST}]);
		my $rsm_slv_rdds_downtime = create_slv_item('RDDS minutes of downtime', 'rsm.slv.rdds.downtime', $hostid,
				VALUE_TYPE_NUM, [$applications->{$hostid}->{APP_SLV_MONTHLY}]);

		create_graph('RDDS Service Availability - Accumulated minutes of downtime', [{'itemid' => $rsm_slv_rdds_downtime, 'hostid' => $hostid}]);

		# NB! Configuration trigger that is used in PHP and C code to detect incident!
		# priority must be set to 0!
		$options = {
			'description' => 'RDDS-AVAIL {HOST.NAME}: 6.2.3 - The service is not available',
			'expression' => '({TRIGGER.VALUE}=0&'.
				'{'.$host_name.':rsm.slv.rdds.avail.count(#{$RSM.INCIDENT.RDDS.FAIL},0,"eq")}={$RSM.INCIDENT.RDDS.FAIL})|'.
				'({TRIGGER.VALUE}=1&'.
				'{'.$host_name.':rsm.slv.rdds.avail.count(#{$RSM.INCIDENT.RDDS.RECOVER},0,"ne")}<{$RSM.INCIDENT.RDDS.RECOVER})',
			'priority' => '0',
		};

		create_trigger($options);

		$options = {
			'description' => 'RDDS has been down for over {$RSM.SLV.RDDS.AVAIL} minutes',
			'expression' => '{'.$host_name.':rsm.slv.rdds.downtime.last(0)}>{$RSM.SLV.RDDS.AVAIL}',
			'priority' => '4'
		};

		create_trigger($options);

		create_slv_item('RDDS weekly unavailability', 'rsm.slv.rdds.rollweek', $hostid, VALUE_TYPE_PERC,
				[$applications->{$hostid}->{APP_SLV_ROLLWEEK}]);

		my $depend_down;

		foreach my $position (sort keys %{$trigger_rollweek_thresholds})
		{
			my $threshold = $trigger_rollweek_thresholds->{$position}->{'threshold'};
			my $priority = $trigger_rollweek_thresholds->{$position}->{'priority'};
			next if ($threshold eq 0);

			$options = {
				'description' => 'RDDS-ROLLWEEK {HOST.NAME}: 6.2.4 - The Service Availability [{ITEM.LASTVALUE1}] >'.$threshold.'%',
				'expression' => '{'.$host_name.':rsm.slv.rdds.rollweek.last(0)}>'.$threshold.'|'.
					'{'.$host_name.':rsm.slv.rdds.rollweek.last(0)}='.$threshold,
				'priority' => $priority,
			};

			my $result = create_trigger($options);

			my $triggerid = $result->{'triggerids'}[0];

			if (defined($depend_down))
			{
				add_dependency($triggerid, $depend_down);
			}

			$depend_down = $triggerid;
		}

		undef($depend_down);

		create_slv_monthly("RDDS Query RTT", "rsm.slv.rdds.rtt", $hostid, $host_name, '{$RSM.SLV.RDDS.RTT}');

		create_slv_monthly("RDDS43 Query RTT", "rsm.slv.rdds43.rtt", $hostid, 0, 0)	# no trigger
			if (defined($OPTS{'rdds43-servers'}));
		create_slv_monthly("RDDS80 Query RTT", "rsm.slv.rdds80.rtt", $hostid, 0, 0)	# no trigger
			if (defined($OPTS{'rdds80-servers'}));
		create_slv_monthly("RDAP Query RTT", "rsm.slv.rdap.rtt", $hostid, 0, 0)		# no trigger
			if (defined($OPTS{'rdap-servers'}));
	}

	create_slv_epp_items($hostid, $host_name) if (defined($OPTS{'epp-servers'}));
}

sub create_slv_epp_items($$)
{
	my $hostid = shift;
	my $host_name = shift;

	create_slv_item('EPP availability', 'rsm.slv.epp.avail', $hostid, VALUE_TYPE_AVAIL,
			[$applications->{$hostid}->{APP_SLV_PARTTEST}]);
	create_slv_item('EPP weekly unavailability', 'rsm.slv.epp.rollweek', $hostid, VALUE_TYPE_PERC,
			[$applications->{$hostid}->{APP_SLV_ROLLWEEK}]);

	my $rsm_slv_epp_downtime = create_slv_item('EPP minutes of downtime', 'rsm.slv.epp.downtime', $hostid,
			VALUE_TYPE_NUM, [$applications->{$hostid}->{APP_SLV_MONTHLY}]);
	create_graph('EPP Service Availability - Accumulated minutes of downtime', [{'itemid' => $rsm_slv_epp_downtime, 'hostid' => $hostid}]);

	create_slv_monthly('EPP Session-Command RTT',   'rsm.slv.epp.rtt.login',  $hostid, $host_name,
			'{$RSM.SLV.EPP.LOGIN}');
	create_slv_monthly('EPP Query-Command RTT',     'rsm.slv.epp.rtt.info',   $hostid, $host_name,
			'{$RSM.SLV.EPP.INFO}');
	create_slv_monthly('EPP Transform-Command RTT', 'rsm.slv.epp.rtt.update', $hostid, $host_name,
			'{$RSM.SLV.EPP.UPDATE}');

	# NB! Configuration trigger that is used in PHP and C code to detect incident!
	# priority must be set to 0!
	my $options = {
		'description' => 'EPP-AVAIL {HOST.NAME}: 7.2.3 - The service is not available',
		'expression' => '({TRIGGER.VALUE}=0&'.
			'{'.$host_name.':rsm.slv.epp.avail.count(#{$RSM.INCIDENT.EPP.FAIL},0,"eq")}={$RSM.INCIDENT.EPP.FAIL})|'.
			'({TRIGGER.VALUE}=1&'.
			'{'.$host_name.':rsm.slv.epp.avail.count(#{$RSM.INCIDENT.EPP.RECOVER},0,"ne")}<{$RSM.INCIDENT.EPP.RECOVER})',
		'priority' => '0',
	};

	create_trigger($options);

	$options = {
		'description' => 'EPP has been down for over {$RSM.SLV.EPP.AVAIL} minutes',
		'expression' => '{'.$host_name.':rsm.slv.epp.downtime.last(0)}>{$RSM.SLV.EPP.AVAIL}',
		'priority' => '4'
	};

	create_trigger($options);

	my $depend_down;

	foreach my $position (sort keys %{$trigger_rollweek_thresholds})
	{
		my $threshold = $trigger_rollweek_thresholds->{$position}->{'threshold'};
		my $priority = $trigger_rollweek_thresholds->{$position}->{'priority'};

		next if ($threshold eq 0);

		$options = {
			'description' => 'EPP-ROLLWEEK {HOST.NAME}: 7.2.4 - The Service Availability [{ITEM.LASTVALUE1}] >'.$threshold.'%',
			'expression' => '{'.$host_name.':rsm.slv.epp.rollweek.last(0)}>'.$threshold.'|'.
				'{'.$host_name.':rsm.slv.epp.rollweek.last(0)}='.$threshold,
			'priority' => $priority,
		};

		my $result = create_trigger($options);

		my $triggerid = $result->{'triggerids'}[0];

		if (defined($depend_down))
		{
			add_dependency($triggerid, $depend_down);
		}

		$depend_down = $triggerid;
	}

	create_slv_monthly("DNS update time", "rsm.slv.dns.upd", $hostid, $host_name, '{$RSM.SLV.DNS.NS.UPD}');
	create_slv_monthly("DNS UDP update time", "rsm.slv.dns.udp.upd", $hostid, 0, 0);	# no trigger
	create_slv_monthly("DNS TCP update time", "rsm.slv.dns.tcp.upd", $hostid, 0, 0);	# no trigger

	if (defined($OPTS{'rdds43-servers'}) || defined($OPTS{'rdap-servers'}))
	{
		create_slv_monthly("RDDS update time", "rsm.slv.rdds.upd", $hostid, $host_name, '{$RSM.SLV.RDDS.UPD}');

		create_slv_monthly("RDDS43 update time", "rsm.slv.rdds43.upd", $hostid, 0, 0)	# no trigger
			if (defined($OPTS{'rdds43-servers'}));
		create_slv_monthly("RDAP update time", "rsm.slv.rdap.upd", $hostid, 0, 0)	# no trigger
			if (defined($OPTS{'rdap-servers'}));
	}
}

# calculated items, configuration history (TODO: rename host to something like config_history)
sub create_rsm_items {
    my $hostid = shift;

    my $options;

    unless (exists($applications->{$hostid}->{'Configuration'})) {
        $applications->{$hostid}->{'Configuration'} = get_application_id('Configuration', $hostid)
    }


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
		        'RSM.DNS.DELAY',
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
			'RSM.SLV.RDDS.RTT',
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
                   'applications' => [$applications->{$hostid}->{'Configuration'}],
                   'params' => '{$'.$macro.'}',
                   'delay' => $delay,
                   'type' => ITEM_TYPE_CALCULATED, 'value_type' => ITEM_VALUE_TYPE_UINT64};

#dotneft bulk?
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
	--only-tld
		deploy only TLD
        --delete
                delete specified TLD
        --disable
                disable specified TLD
        --only-epp
                action with EPP only
	--list-services
		list services of each TLD, the output is comma-separated list:
                <TLD>,<TLD-TYPE>,<RDDS.DNS.TESTPREFIX>,<RDDS.NS.STRING>,<RDDS.TESTPREFIX>,<TLD.DNSSEC.ENABLED>,<TLD.EPP.ENABLED>,<TLD.RDDS43.ENABLED>,<TLD.RDDS80.ENABLED>,<TLD.RDAP.ENABLED>
	--get-nsservers-list
		CSV formatted list of NS + IP server pairs for specified TLD
	--update-nsservers
		update all NS + IP pairs for specified TLD. --ns-servers-v4 or/and --ns-servers-v6 is mandatory in this case
        --type=STRING
                Type of TLD. Possible values: @{[TLD_TYPE_G]}, @{[TLD_TYPE_CC]}, @{[TLD_TYPE_OTHER]}, @{[TLD_TYPE_TEST]}.
        --set-type
                set specified TLD type and exit
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
                list of RDDS80 server URLs separated by comma: "NAME1,NAME2,..."
	--rdap-servers=STRING
		list of RDAP server URLs separated by comma: "NAME1,NAME2,..."
        --epp-servers=STRING
                list of EPP servers separated by comma: "NAME1,NAME2,..."
        --epp-user
                specify EPP username
	--epp-cert
                path to EPP Client certificate file
	--epp-servercert
                path to EPP Server certificate file
	--epp-privkey
                path to EPP Client private key file (unencrypted)
	--epp-serverid
                specify expected EPP Server ID string in reply
	--epp-test-prefix=STRING
                this string represents DOMAIN (in DOMAIN.TLD) to use in EPP commands
	--dns-test-prefix-epp=STRING
                this string represents DOMAIN (in DOMAIN.TLD) to use in DNS test for "update time" check
	--epp-commands
                path to a directory on the Probe Node containing EPP command templates
		(default: /opt/test-sla/epp-commands/TLD)
        --rdds-ns-string=STRING
                name server prefix in the WHOIS output
		(default: $cfg_default_rdds_ns_string)
        --rdds-test-prefix=STRING
		domain test prefix for RDDS monitoring (needed only if RDDS43 servers specified)
        --setup-cron
		create cron jobs and exit
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

    return if (defined($OPTS{'setup-cron'}));

    $msg  = "TLD must be specified (--tld)\n" if (!defined($OPTS{'tld'}) and !defined($OPTS{'get-nsservers-list'}) and !defined($OPTS{'list-services'}));

    if (!defined($OPTS{'delete'}) and !defined($OPTS{'only-epp'}) and !defined($OPTS{'disable'}) and !defined($OPTS{'get-nsservers-list'}) and !defined($OPTS{'update-nsservers'}) and !defined($OPTS{'list-services'}))
    {
	    if (!defined($OPTS{'type'}))
	    {
		    $msg .= "type (--type) of TLD must be specified: @{[TLD_TYPE_G]}, @{[TLD_TYPE_CC]}, @{[TLD_TYPE_OTHER]} or @{[TLD_TYPE_TEST]}\n";
	    }
	    elsif ($OPTS{'type'} ne TLD_TYPE_G and $OPTS{'type'} ne TLD_TYPE_CC and $OPTS{'type'} ne TLD_TYPE_OTHER and $OPTS{'type'} ne TLD_TYPE_TEST)
	    {
		    $msg .= "invalid TLD type \"${OPTS{'type'}}\", type must be one of: @{[TLD_TYPE_G]}, @{[TLD_TYPE_CC]}, @{[TLD_TYPE_OTHER]} or @{[TLD_TYPE_TEST]}\n";
	    }
    }

    if (defined($OPTS{'set-type'}))
    {
	    unless ($msg eq "") {
		    print($msg);
		    usage();
	    }
	    return;
    }

    $msg .= "at least one IPv4 or IPv6 must be enabled (--ipv4 or --ipv6)\n" if (!defined($OPTS{'delete'}) and !defined($OPTS{'disable'}) and !defined($OPTS{'only-epp'})
										and !defined($OPTS{'ipv4'}) and !defined($OPTS{'ipv6'})
										and !defined($OPTS{'get-nsservers-list'}) and !defined($OPTS{'update-nsservers'})
										and !defined($OPTS{'list-services'}));
    $msg .= "DNS test prefix must be specified (--dns-test-prefix)\n" if (!defined($OPTS{'delete'}) and !defined($OPTS{'disable'}) and !defined($OPTS{'only-epp'}) and !defined($OPTS{'dns-test-prefix'})
									    and !defined($OPTS{'get-nsservers-list'}) and !defined($OPTS{'update-nsservers'})
									    and !defined($OPTS{'list-services'}));

	if ((defined($OPTS{$rsm_rdds_interfaces->{'RDDS43'}->{'option'}}) && !defined($OPTS{'rdds-test-prefix'})))
	{
		$msg .= "RDDS test prefix must be specified (--rdds-test-prefix)\n";
	}

    if ($OPTS{'epp-servers'} or defined($OPTS{'only-epp'})) {
	$msg .= "EPP user must be specified (--epp-user)\n" unless ($OPTS{'epp-user'});
	$msg .= "EPP server ID must be specified (--epp-serverid)\n" unless ($OPTS{'epp-serverid'});
	$msg .= "EPP domain test prefix must be specified (--epp-test-prefix)\n" unless ($OPTS{'epp-test-prefix'});
	$msg .= "DNS domain test prefix for \"update time\" check must be specified (--dns-test-prefix-epp)\n" unless ($OPTS{'dns-test-prefix-epp'});
	if (!$OPTS{'epp-cert'}) {
	    $msg .= "EPP Client certificate file must be specified (--epp-cert)\n";
	} elsif (! -r $OPTS{'epp-cert'}) {
	    $msg .= "Cannot read EPP Client certificate file \"" . $OPTS{'epp-cert'} . "\"\n";
	}
	if (!$OPTS{'epp-servercert'}) {
	    $msg .= "EPP Server certificate file must be specified (--epp-servercert)\n";
	} elsif (! -r $OPTS{'epp-servercert'}) {
	    $msg .= "Cannot read EPP Server certificate file \"" . $OPTS{'epp-servercert'} . "\"\n";
	}
	if (!$OPTS{'epp-privkey'}) {
	    $msg .= "EPP Client private key file must be specified (--epp-privkey)\n";
	} elsif(! -r $OPTS{'epp-privkey'}) {
	    $msg .= "Cannot read EPP Client private key file \"" . $OPTS{'epp-privkey'} . "\"\n";
	}
    }

    $OPTS{'ipv4'} = 0 if (defined($OPTS{'update-nsservers'}));
    $OPTS{'ipv6'} = 0 if (defined($OPTS{'update-nsservers'}));

    $OPTS{'dns'} = 0 unless defined $OPTS{'dns'};
    $OPTS{'rdds'} = 0 unless defined $OPTS{'rdds'};
    $OPTS{'epp'} = 0 unless defined $OPTS{'epp'};
    $OPTS{'only-tld'} = false unless defined $OPTS{'only-tld'};

    unless ($msg eq "") {
	print($msg);
	usage();
    }
}

sub lc_options
{
	my @options_to_lowercase = ("tld", "epp-servers", "ns-servers-v4", "ns-servers-v6",
			$rsm_rdds_interfaces->{'RDDS43'}->{'option'},
			$rsm_rdds_interfaces->{'RDDS80'}->{'option'},
			$rsm_rdds_interfaces->{'RDAP'}->{'option'},);

	foreach my $option (@options_to_lowercase)
	{
	    $OPTS{$option} = lc($OPTS{$option}) if (exists($OPTS{$option}));
	}
}

sub add_default_actions() {

}

sub create_global_macros() {
    my $global_macros = {
	'{$RSM.IP4.MIN.PROBE.ONLINE}' => 2,
        '{$RSM.IP6.MIN.PROBE.ONLINE}' => 2,

        '{$RSM.IP4.MIN.SERVERS}' => 4,
        '{$RSM.IP6.MIN.SERVERS}' => 4,
        '{$RSM.IP4.REPLY.MS}' => 500,
        '{$RSM.IP6.REPLY.MS}' => 500,

        '{$RSM.DNS.TCP.RTT.LOW}' => 1500,
        '{$RSM.DNS.TCP.RTT.HIGH}' => 7500,
        '{$RSM.DNS.UDP.RTT.LOW}' => 500,
        '{$RSM.DNS.UDP.RTT.HIGH}' => 2500,
        '{$RSM.DNS.DELAY}' => 60,
        '{$RSM.DNS.UPDATE.TIME}' => 3600,
        '{$RSM.DNS.PROBE.ONLINE}' => 2,
        '{$RSM.DNS.AVAIL.MINNS}' => 2,
        '{$RSM.DNS.ROLLWEEK.SLA}' => 60,
        '{$RSM.DNS.TEST.PROTO.RATIO}' => 10,
        '{$RSM.DNS.TEST.CRIT.RECOVER}' => 3,
        '{$RSM.DNS.TEST.UPD.RATIO}' => 2,

        '{$RSM.RDDS.RTT.LOW}' => 2000,
        '{$RSM.RDDS43.RTT.HIGH}' => 10000,
        '{$RSM.RDDS80.RTT.HIGH}' => 10000,
        '{$RSM.RDAP.RTT.HIGH}' => 10000,
        '{$RSM.RDDS.DELAY}' => 60,
        '{$RSM.RDDS.UPDATE.TIME}' => 3600,
        '{$RSM.RDDS.PROBE.ONLINE}' => 2,
        '{$RSM.RDDS.ROLLWEEK.SLA}' => 60,
        '{$RSM.RDDS80.MAXREDIRS}' => 10,
        '{$RSM.RDAP.MAXREDIRS}' => 10,

        '{$RSM.EPP.DELAY}' => 60,
        '{$RSM.EPP.LOGIN.RTT.LOW}' => 4000,
        '{$RSM.EPP.LOGIN.RTT.HIGH}' => 2000,
        '{$RSM.EPP.UPDATE.RTT.LOW}' => 4000,
        '{$RSM.EPP.UPDATE.RTT.HIGH}' => 20000,
        '{$RSM.EPP.INFO.RTT.LOW}' => 2000,
        '{$RSM.EPP.INFO.RTT.HIGH}' => 10000,
        '{$RSM.EPP.PROBE.ONLINE}' => 2,
        '{$RSM.EPP.ROLLWEEK.SLA}' => 60,

        '{$RSM.PROBE.ONLINE.DELAY}' => 60,

        '{$RSM.TRIG.DOWNCOUNT}' => '#1',
        '{$RSM.TRIG.UPCOUNT}' => '#3',

        '{$RSM.INCIDENT.DNS.FAIL}' => 3,
        '{$RSM.INCIDENT.DNS.RECOVER}' => 3,
        '{$RSM.INCIDENT.DNSSEC.FAIL}' => 3,
        '{$RSM.INCIDENT.DNSSEC.RECOVER}' => 3,
        '{$RSM.INCIDENT.RDDS.FAIL}' => 2,
        '{$RSM.INCIDENT.RDDS.RECOVER}' => 2,
        '{$RSM.INCIDENT.EPP.FAIL}' => 2,
        '{$RSM.INCIDENT.EPP.RECOVER}' => 2,

        '{$RSM.SLV.DNS.UDP.RTT}' => 95,
        '{$RSM.SLV.DNS.TCP.RTT}' => 95,
        '{$RSM.SLV.DNS.AVAIL}' => 0,
        '{$RSM.SLV.NS.AVAIL}' => 432,
        '{$RSM.SLV.RDDS43.RTT}' => 95,
        '{$RSM.SLV.RDDS80.RTT}' => 95,
        '{$RSM.SLV.RDDS.UPD}' => 95,
	'{$RSM.SLV.RDDS.RTT}' => 95,
        '{$RSM.SLV.RDDS.AVAIL}' => 864,
        '{$RSM.SLV.DNS.NS.UPD}' => 95,
        '{$RSM.SLV.EPP.LOGIN}' => 90,
        '{$RSM.SLV.EPP.UPDATE}' => 90,
        '{$RSM.SLV.EPP.INFO}' => 90,
        '{$RSM.SLV.EPP.AVAIL}' => 864,

        '{$RSM.ROLLWEEK.THRESHOLDS}' => RSM_ROLLWEEK_THRESHOLDS,
        '{$RSM.ROLLWEEK.SECONDS}' => 7200,
        '{$RSM.PROBE.AVAIL.LIMIT}' => '60'  # For finding unreachable probes. Probes are considered unreachable if last access time is over this limit of seconds.
    };

    bulk_macro_create($global_macros, undef);	# do not force update
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
    my $template_data = create_template('Template Proxy Health', LINUX_TEMPLATEID);
    my $templateid = $template_data->{'templateid'};
    my $is_new = $template_data->{'is_new'};

    my $item_key = 'zabbix[proxy,{$RSM.PROXY_NAME},lastaccess]';

    unless (exists($applications->{$templateid}->{'Probe Availability'})) {
        $applications->{$templateid}->{'Probe Availability'} = get_application_id('Probe Availability', $templateid);
    }

    my $options = {'name' => 'Availability of $2 Probe',
                                          'key_'=> $item_key,
                                          'hostid' => $templateid,
                                          'applications' => [$applications->{$templateid}->{'Probe Availability'}],
                                          'type' => 5, 'value_type' => 3,
                                          'units' => 'unixtime', delay => '60'};

    create_item($options);

    $options = { 'description' => 'PROBE {HOST.NAME}: Probe {$RSM.PROXY_NAME} is not available',
                     'expression' => '{Template Proxy Health:'.$item_key.'.fuzzytime(2m)}=0',
                    'priority' => '4',
            };

    create_trigger($options);

    $options = {'name' => 'Probe main status',
		'key_'=> 'rsm.probe.online',
		'hostid' => $templateid,
		'applications' => [get_application_id('Probe Availability', $templateid)],
		'type' => 2, 'value_type' => 3,
		'valuemapid' => rsm_value_mappings->{'rsm_probe'}};

    create_item($options);

    return $templateid;
}

sub manage_tld_objects($$$$$) {
    my $action = shift;
    my $tld = shift;
    my $dns = shift;
    my $epp = shift;
    my $rdds = shift;

    my $types = {'dns' => $dns, 'epp' => $epp, 'rdds' => $rdds};

    my @tld_hostids;

    print "Trying to $action '$tld' TLD\n";

    print "Getting main host of the TLD: ";
    my $main_hostid = get_host($tld, false);

    if (scalar(%{$main_hostid})) {
        $main_hostid = $main_hostid->{'hostid'};
	print "success\n";
    }
    else {
        pfail "Could not find '$tld' host";
    }

    print "Getting main template of the TLD: ";
    my $tld_template = get_template('Template '.$tld, false, true);

    if (scalar(%{$tld_template})) {
        $main_templateid = $tld_template->{'templateid'};
	print "success\n";
    }
    else {
        pfail "Could not find 'Template .$tld' template";
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
	foreach $b (@{$array_B}) {
	    $found = true if $a eq $b;
	}

	push @result, $a if $found eq false;
    }

    return @result;
}

sub get_tld_list() {
    my $tlds = get_host_group('TLDs', true);

    my @result;

    foreach my $tld (@{$tlds->{'hosts'}}) {
	push @result, $tld->{'name'};
    }

    return @result;
}

sub get_nsservers_list($) {
    my $TLD = shift;

    my $result;

    my $templateid = get_template('Template '.$TLD, false, false);

    return unless defined $templateid->{'templateid'};

    $templateid = $templateid->{'templateid'};

    my $items = get_items_like($templateid, 'rsm.dns.tcp.rtt', true);

    foreach my $itemid (keys %{$items}) {
	next if $items->{$itemid}->{'status'} == ITEM_STATUS_DISABLED;

	my $name = $items->{$itemid}->{'key_'};
	my $ip = $items->{$itemid}->{'key_'};

	$ip =~s/.+\,.+\,(.+)\]$/$1/;
	$name =~s/.+\,(.+)\,.+\]$/$1/;

	if ($ip=~/\d*\.\d*\.\d*\.\d+/) {
	    push @{$result->{'v4'}->{$name}}, $ip;
	}
	else {
	    push @{$result->{'v6'}->{$name}}, $ip;
	}
    }

    return $result;
}

sub update_nsservers($$) {
    my $TLD = shift;
    my $new_ns_servers = shift;

    return unless defined $new_ns_servers;

    my $old_ns_servers = get_nsservers_list($TLD);

    return unless defined $old_ns_servers;

    my @to_be_added = ();
    my @to_be_removed = ();

    foreach my $new_nsname (keys %{$new_ns_servers}) {
	my $new_ns = $new_ns_servers->{$new_nsname};

	foreach my $ipv (keys %{$new_ns}) {
	    my $new_ips = $new_ns->{$ipv};
	    foreach my $new_ip (@{$new_ips}) {
		    my $need_to_add = true;

		    if (defined($old_ns_servers->{$ipv}) and defined($old_ns_servers->{$ipv}->{$new_nsname})) {
			foreach my $old_ip (@{$old_ns_servers->{$ipv}->{$new_nsname}}) {
			    $need_to_add = false if $old_ip eq $new_ip;
			}
		    }

		    if ($need_to_add == true) {
			my $ns_ip;
			$ns_ip->{$new_ip}->{'ns'} = $new_nsname;
			$ns_ip->{$new_ip}->{'ipv'} = $ipv;
			push @to_be_added, $ns_ip;
		    }
	    }

	}
    }

    foreach my $ipv (keys %{$old_ns_servers}) {
	my $old_ns = $old_ns_servers->{$ipv};
	foreach my $old_nsname (keys %{$old_ns}) {
	    foreach my $old_ip (@{$old_ns->{$old_nsname}}) {
		my $need_to_remove = false;

		if (defined($new_ns_servers->{$old_nsname}->{$ipv})) {
		    $need_to_remove = true;

		    foreach my $new_ip (@{$new_ns_servers->{$old_nsname}->{$ipv}}) {
		    	    $need_to_remove = false if $new_ip eq $old_ip;
		        }
		}
		else {
		    $need_to_remove = true;
		}

		if ($need_to_remove == true) {
		    my $ns_ip;

		    $ns_ip->{$old_ip} = $old_nsname;

		    push @to_be_removed, $ns_ip;
		}
	    }
	}
    }

    add_new_ns($TLD, \@to_be_added) if scalar(@to_be_added);
    disable_old_ns($TLD, \@to_be_removed) if scalar(@to_be_removed);
}

sub add_new_ns($$) {
    my $TLD = shift;
    my $ns_servers = shift;

    my $main_templateid = get_template('Template '.$TLD, false, false);

    return unless defined $main_templateid->{'templateid'};

    $main_templateid = $main_templateid->{'templateid'};

    my $main_hostid = get_host($TLD, false);

    return unless defined $main_hostid->{'hostid'};

    $main_hostid = $main_hostid->{'hostid'};

    my $macro_value = get_host_macro($main_templateid, '{$RSM.TLD.DNSSEC.ENABLED}');

    $OPTS{'dnssec'} = true if (defined($macro_value) and $macro_value->{'value'} eq true);

    $macro_value = get_host_macro($main_templateid, '{$RSM.TLD.EPP.ENABLED}');

    $OPTS{'epp-servers'} = true if (defined($macro_value) and $macro_value->{'value'} eq true);

    foreach my $ns_ip (@$ns_servers) {
	foreach my $ip (keys %{$ns_ip}) {
	    my $ipv = $ns_ip->{$ip}->{'ipv'};
	    my $ns = $ns_ip->{$ip}->{'ns'};

	    $ipv=~s/v(\d)/$1/;

	    create_item_dns_rtt($ns, $ip, $main_templateid, 'Template '.$TLD, 'tcp', $ipv);
	    create_item_dns_rtt($ns, $ip, $main_templateid, 'Template '.$TLD, 'udp', $ipv);
	    create_item_dns_udp_upd($ns, $ip, $main_templateid, 'Template '.$TLD, $ipv) if (defined($OPTS{'epp-servers'}));

	    create_item_dns_udp_upd($ns, $ip, $main_templateid, 'Template '.$TLD, $ipv) if (defined($OPTS{'epp-servers'}));

    	    create_all_slv_ns_items($ns, $ip, $TLD, $main_hostid);
	}
    }
}

sub disable_old_ns($) {
    my $TLD = shift;
    my $ns_servers = shift;

    my @itemids;

    my $main_templateid = get_template('Template '.$TLD, false, false);

    return unless defined $main_templateid->{'templateid'};

    $main_templateid = $main_templateid->{'templateid'};

    my $main_hostid = get_host($TLD, false);

    return unless defined $main_hostid->{'hostid'};

    $main_hostid = $main_hostid->{'hostid'};

    foreach my $ns (@$ns_servers) {
	foreach my $ip (keys %{$ns}) {
	    my $ns_name = $ns->{$ip};
	    my $item_key = ','.$ns_name.','.$ip.']';

	    my $items = get_items_like($main_templateid, $item_key, true);

	    my @tmp_items = keys %{$items};

	    push @itemids, @tmp_items;

	    $item_key = '['.$ns_name.','.$ip.']';

	    $items = get_items_like($main_hostid, $item_key, false);

    	    @tmp_items = keys %{$items};

    	    push @itemids, @tmp_items;
	}
    }

    if (scalar(@itemids) > 0) {
	my $triggers = get_triggers_by_items(\@itemids);

        my @triggerids = keys %{$triggers};

#	disable_triggers(\@triggerids) if scalar @triggerids;

    	disable_items(\@itemids);
    }
}

sub get_services($) {
    my $tld = shift;

    my @tld_types = [TLD_TYPE_G, TLD_TYPE_CC, TLD_TYPE_OTHER, TLD_TYPE_TEST];

    my $result;

    my $templateid = get_template('Template '.$tld, false, false);

    my $macros = get_host_macro($templateid, undef);

    my $tld_host = get_host($tld, true);

    foreach my $group (@{$tld_host->{'groups'}}) {
	my $name = $group->{'name'};
	$result->{'tld_type'} = $name if ($name ~~ @tld_types);
    }

    foreach my $macro (@{$macros}) {
	my $name = $macro->{'macro'};
	my $value = $macro->{'value'};

	$result->{$name} = $value;
    }

    return $result;
}

sub update_epp_objects($) {
    my $tld = shift;

    my $is_new = false;

    print "Getting main host of the TLD: ";
    my $result = get_host($tld, false);

    if (!scalar(%{$result})) {
	pfail "Could not find '$tld' host";
    }

    my $hostid = $result->{'hostid'};
    print "success\n";

    print "Getting main template of the TLD: ";
    $result = get_template('Template '.$tld, true, false);

    if (!scalar(%{$result})) {
	pfail "Could not find 'Template .$tld' template";
    }

    my $templateid = $result->{'templateid'};
    print "success\n";

    create_slv_epp_items($hostid, $tld);

    create_macro('{$RSM.TLD.EPP.ENABLED}', 1, $templateid, true, $is_new);

    create_epp_objects($templateid, 'Template '.$tld, $tld, $is_new);

    create_slv_monthly("DNS update time", "rsm.slv.dns.udp.upd", $hostid, $tld, '{$RSM.SLV.DNS.NS.UPD}');

    if (defined($OPTS{'rdds43-servers'}) || defined($OPTS{'rdap-servers'}))
    {
	    create_slv_monthly("RDDS update time", "rsm.slv.rdds.upd", $hostid, $tld, '{$RSM.SLV.RDDS.UPD}');

	    create_slv_monthly("RDDS43 update time", "rsm.slv.rdds43.upd", $hostid, 0, 0)	# no trigger
		    if (defined($OPTS{'rdds43-servers'}));
	    create_slv_monthly("RDAP update time", "rsm.slv.rdap.upd", $hostid, 0, 0)		# no trigger
		    if (defined($OPTS{'rdap-servers'}));
    }

    my $ns_servers = get_nsservers_list($tld);

    foreach my $ipv (keys %{$ns_servers}) {
	my $ns_by_ipv = $ns_servers->{$ipv};

	$ipv =~s/v([46])/$1/;

        foreach my $ns_name (keys %{$ns_by_ipv}) {
            my $ns = $ns_by_ipv->{$ns_name};
            foreach my $ip (@{$ns}) {
		create_item_dns_udp_upd($ns_name, $ip, $templateid, 'Template '.$tld, $ipv, $is_new);
            }
        }
    }
}

sub create_slv_monthly($$$$$)
{
    my $test_name = shift;
    my $key_base = shift;
    my $hostid = shift;
    my $host_name = shift;
    my $macro = shift;

    unless (exists($applications->{$hostid}->{APP_SLV_MONTHLY})) {
	$applications->{$hostid}->{APP_SLV_MONTHLY} = get_application_id(APP_SLV_MONTHLY, $hostid)
    }

    my $applicationid = $applications->{$hostid}->{APP_SLV_MONTHLY};

    my $pfailed_item = create_slv_item($test_name.': % of failed tests', $key_base.'.pfailed', $hostid, VALUE_TYPE_PERC, [$applicationid]);
    create_slv_item($test_name.': # of failed tests', $key_base.'.failed', $hostid, VALUE_TYPE_NUM, [$applicationid]);
    create_slv_item($test_name.': expected # of tests', $key_base.'.max', $hostid, VALUE_TYPE_NUM, [$applicationid]);
    my $avg_item = create_slv_item($test_name.': average cycle result', $key_base.'.avg', $hostid, VALUE_TYPE_DOUBLE, [$applicationid]);

    create_graph($test_name.' - Average', [{'itemid' => $avg_item, 'hostid' => $hostid}]);

    if ($host_name)
    {
	    create_graph($test_name.' - Ratio of Failed tests', [{'itemid' => $pfailed_item, 'hostid' => $hostid}]);

	    my $options = {
		    'description' => $test_name . ' < ' . $macro . '%',
		    'expression' => '{'.$host_name.':'.$key_base.'.pfailed.last(0)}<'.$macro,
		    'priority' => '4'
	    };

	    create_trigger($options);
    }
}
