package TLDs;

use strict;
use warnings;
use Zabbix;
use TLD_constants qw(:general :templates :api :config);
use Data::Dumper;
use base 'Exporter';

our @EXPORT = qw(zbx_connect check_api_error get_proxies_list
		create_probe_status_host
		create_probe_template create_probe_status_template create_host create_group create_template create_item create_trigger create_macro update_root_servers
		create_passive_proxy is_probe_exist get_host_group get_template get_probe get_host
		remove_templates remove_hosts remove_hostgroups remove_probes remove_items
		disable_host disable_hosts
		disable_items disable_triggers
		macro_value get_global_macro_value get_host_macro
		set_proxy_status
		get_application_id get_items_like set_tld_type get_triggers_by_items
		add_dependency
		create_cron_jobs
		pfail);

our ($zabbix, $result);

sub zbx_connect($$$) {
    my $url = shift;
    my $user = shift;
    my $password = shift;

    $zabbix = Zabbix->new({'url' => $url, user => $user, password => $password});

    return $zabbix->{'error'} if defined($zabbix->{'error'}) and $zabbix->{'error'} ne '';

    return true;
}

sub check_api_error($) {
    my $result = shift;

    return true if 'HASH' eq ref($result) and (defined $result->{'error'} or defined $result->{'code'});

    return false;
}

sub get_proxies_list {
    my $proxies_list;

    $proxies_list = $zabbix->get('proxy',{'output' => ['proxyid', 'host'], 'selectInterfaces' => ['ip'],
					  'filter' => {'status' => HOST_STATUS_PROXY_PASSIVE}, 'preservekeys' => 1 });

    return $proxies_list;
}

sub is_probe_exist($) {
    my $name = shift;

    my $result = $zabbix->get('proxy',{'output' => ['proxyid'], 'filter' => {'host' => $name}, 'preservekeys' => 1 });

    return (keys %{$result}) ? true : false;
}

sub get_probe($$) {
    my $probe_name = shift;
    my $selectHosts = shift;

    my $options = {'output' => ['proxyid', 'host'], 'filter' => {'host' => $probe_name}};

    $options->{'selectHosts'} = ['hostid', 'name', 'host'] if (defined($selectHosts) and $selectHosts eq true);

    my $result = $zabbix->get('proxy', $options);

    return $result;
}

sub get_host_group($$) {
    my $group_name = shift;
    my $selectHosts = shift;

    my $options = {'output' => 'extend', 'filter' => {'name' => $group_name}};

    $options->{'selectHosts'} = ['hostid', 'host', 'name'] if (defined($selectHosts) and $selectHosts eq true);

    my $result = $zabbix->get('hostgroup', $options);

    return $result;
}

sub get_template($$$) {
    my $template_name = shift;
    my $selectMacros = shift;
    my $selectHosts = shift;

    my $options = {'output' => ['templateid', 'host'], 'filter' => {'host' => $template_name}};

    $options->{'selectMacros'} = 'extend' if (defined($selectMacros) and $selectMacros eq true);

    $options->{'selectHosts'} = ['hostid', 'host'] if (defined($selectHosts) and $selectHosts eq true);

    my $result = $zabbix->get('template', $options);

    return $result;
}

sub remove_templates($) {
    my @templateids = shift;

    return unless scalar(@templateids);

    my $result = $zabbix->remove('template', @templateids);

    return $result;
}

sub remove_hosts($) {
    my @hosts = shift;

    return unless scalar(@hosts);

    my $result = $zabbix->remove('host', @hosts);

    return $result;
}

sub disable_hosts($) {
    my @hosts = shift;

    return unless scalar(@hosts);

    my $result = $zabbix->massupdate('host', {'hosts' => @hosts, 'status' => HOST_STATUS_NOT_MONITORED});

    return $result;
}

sub remove_hostgroups($) {
    my @hostgroupids = shift;

    return unless scalar(@hostgroupids);

    my $result = $zabbix->remove('hostgroup', @hostgroupids);

    return $result;
}

sub remove_probes($) {
    my @probes = shift;

    return unless scalar(@probes);

    my $result = $zabbix->remove('proxy', @probes);

    return $result;
}

sub disable_items($) {
    my $items = shift;

    return unless scalar(@{$items});

    my $result;

    foreach my $itemid (@{$items}) {
	$result->{$itemid} = $zabbix->update('item', {'itemid' => $itemid, 'status' => ITEM_STATUS_DISABLED});
    }

    return $result;
}

sub disable_triggers($) {
    my $triggers = shift;

    return unless scalar(@{$triggers});

    my $result;

    foreach my $triggerid (@{$triggers}) {
	$result->{$triggerid} = $zabbix->update('trigger', {'triggerid' => $triggerid, 'status' => TRIGGER_STATUS_DISABLED});
    }

    return $result;
}

sub remove_items($) {
    my $items = shift;

    return unless scalar(@{$items});

    my $result = $zabbix->remove('item', $items );

    return $result;
}


sub disable_host($) {
    my $hostid = shift;

    return unless defined($hostid);

    my $result = $zabbix->update('host', {'hostid' => $hostid, 'status' => HOST_STATUS_NOT_MONITORED});

    return $result;
}

sub macro_value($$) {
    my $hostmacroid = shift;
    my $value = shift;

    return if !defined($hostmacroid) or !defined($value);

    my $result = $zabbix->update('usermacro', {'hostmacroid' => $hostmacroid, 'value' => $value});

    return $result;
}

sub set_proxy_status($$) {
    my $proxyid = shift;
    my $status = shift;

    return if !defined($proxyid) or !defined($status);

    return if $status != HOST_STATUS_PROXY_ACTIVE and $status != HOST_STATUS_PROXY_PASSIVE;

    my $result = $zabbix->update('proxy', { 'proxyid' => $proxyid, 'status' => $status});

    return $result;
}

sub get_host($$) {
    my $host_name = shift;
    my $selectGroups = shift;

    my $options = {'output' => ['hostid', 'host'], 'filter' => {'host' => $host_name} };

    $options->{'selectGroups'} = 'extend' if (defined($selectGroups) and $selectGroups eq true);

    my $result = $zabbix->get('host', $options);

    return $result;
}

sub get_global_macro_value($) {
    my $macro_name = shift;

    my $options = {'globalmacro' => true, output => 'extend', 'filter' => {'macro' => $macro_name}};

    my $result = $zabbix->get('usermacro', $options);

    return $result->{'value'} if defined($result->{'value'});
}


sub update_root_servers {
    my $content = LWP::UserAgent->new->get('http://www.internic.net/zones/named.root')->{'_content'};

    my $macro_value_v4 = "";
    my $macro_value_v6 = "";

    return unless defined $content;

    for my $str (split("\n", $content)) {
	if ($str=~/.+ROOT\-SERVERS.+\sA\s+(.+)$/) {
	    $macro_value_v4 .= ',' if ($macro_value_v4 ne "");
	    $macro_value_v4 .= $1;
	}

	if ($str=~/.+ROOT\-SERVERS.+AAAA\s+(.+)$/) {
	    $macro_value_v6 .= ',' if ($macro_value_v6 ne "");
	    $macro_value_v6 .= $1;
        }
    }

# Temporary disable the check
#    return unless create_macro('{$RSM.IP4.ROOTSERVERS1}', $macro_value_v4) eq true;
#    return unless create_macro('{$RSM.IP6.ROOTSERVERS1}', $macro_value_v6) eq true;
    create_macro('{$RSM.IP4.ROOTSERVERS1}', $macro_value_v4);
    create_macro('{$RSM.IP6.ROOTSERVERS1}', $macro_value_v6);

    return '"{$RSM.IP4.ROOTSERVERS1}","{$RSM.IP6.ROOTSERVERS1}"';
}

sub create_host {
    my $options = shift;

    unless ($zabbix->exist('host',{'name' => $options->{'host'}})) {
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

    my ($result, $templateid, $options, $groupid);

    unless ($zabbix->exist('hostgroup', {'name' => 'Templates - TLD'})) {
        $result = $zabbix->create('hostgroup', {'name' => 'Templates - TLD'});
        $groupid = $result->{'groupids'}[0];
    }
    else {
        $result = $zabbix->get('hostgroup', {'filter' => {'name' => 'Templates - TLD'}});
        $groupid = $result->{'groupid'};
    }

    return $zabbix->last_error if defined $zabbix->last_error;

    unless ($zabbix->exist('template',{'host' => $name})) {
        $options = {'groups'=> {'groupid' => $groupid}, 'host' => $name};

        $options->{'templates'} = [{'templateid' => $child_templateid}] if defined $child_templateid;

        $result = $zabbix->create('template', $options);

        $templateid = $result->{'templateids'}[0];
    }
    else {
        $result = $zabbix->get('template', {'filter' => {'host' => $name}});
        $templateid = $result->{'templateid'};

        $options = {'templateid' => $templateid, 'groups'=> {'groupid' => $groupid}, 'host' => $name};
        $options->{'templates'} = [{'templateid' => $child_templateid}] if defined $child_templateid;

        $result = $zabbix->update('template', $options);
        $templateid = $result->{'templateids'}[0];
    }

    return $zabbix->last_error if defined $zabbix->last_error;

    return $templateid;
}

sub create_item {
    my $options = shift;
    my $result;

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

    return $zabbix->last_error if defined $zabbix->last_error;

    $result = ${$result->{'itemids'}}[0] if (defined(${$result->{'itemids'}}[0]));

#    pfail("cannot create item:\n", Dumper($options)) if (ref($result) ne '' or $result eq '');

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

#    pfail("cannot create trigger:\n", Dumper($options)) if (ref($result) ne '' or $result eq '');

    return $result;
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
    	    $zabbix->update('usermacro',{'hostmacroid' => $result->{'hostmacroid'}, 'value' => $value}) if defined $result->{'hostmacroid'}
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
            $zabbix->macro_global_update({'globalmacroid' => $result->{'globalmacroid'}, 'value' => $value}) if defined $result->{'globalmacroid'}
															and defined($force_update);
        }
        else {
            $result = $zabbix->macro_global_create({'macro' => $name, 'value' => $value});
        }

	return $result->{'globalmacroids'}[0];
    }

}

sub get_host_macro {
    my $templateid = shift;
    my $name = shift;
    
    my $result;

    $result = $zabbix->get('usermacro',{'hostids' => $templateid, 'output' => 'extend', 'filter' => {'macro' => $name}});

    return $result;
}

sub create_passive_proxy($$) {
    my $probe_name = shift;
    my $probe_ip = shift;

    my $probe = get_probe($probe_name, false);

    if (defined($probe->{'proxyid'})) {
	my $result = $zabbix->update('proxy', {'proxyid' => $probe->{'proxyid'}, 'status' => HOST_STATUS_PROXY_PASSIVE,
                                        'interfaces' => [{'ip' => $probe_ip, 'dns' => '', 'useip' => true, 'port' => '10051'}]});

	if (scalar($result->{'proxyids'})) {
            return $result->{'proxyids'}[0];
        }
    }
    else {
        my $result = $zabbix->create('proxy', {'host' => $probe_name, 'status' => HOST_STATUS_PROXY_PASSIVE,
                                        'interfaces' => [{'ip' => $probe_ip, 'dns' => '', 'useip' => true, 'port' => '10051'}],
                                        'hosts' => []});
	if (scalar($result->{'proxyids'})) {
	    return $result->{'proxyids'}[0];
	}
    }

    return;
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



sub create_probe_template {
    my $root_name = shift;
    my $epp = shift;
    my $ipv4 = shift;
    my $ipv6 = shift;
    my $rdds = shift;
    my $resolver = shift;

    my $templateid = create_template('Template '.$root_name);

    create_macro('{$RSM.IP4.ENABLED}', defined($ipv4) ? $ipv4 : '1', $templateid);
    create_macro('{$RSM.IP6.ENABLED}', defined($ipv6) ? $ipv6 : '1', $templateid);
    create_macro('{$RSM.RESOLVER}', defined($resolver) ? $resolver : '127.0.0.1', $templateid);
    create_macro('{$RSM.RDDS.ENABLED}', defined($rdds) ? $rdds : '1', $templateid);
    create_macro('{$RSM.EPP.ENABLED}', defined($epp) ? $epp : '1', $templateid);

    return $templateid;
}

sub create_probe_status_template {
    my $probe_name = shift;
    my $child_templateid = shift;
    my $root_servers_macros = shift;

    my $template_name = 'Template '.$probe_name.' Status';

    my $templateid = create_template($template_name, $child_templateid);

    my $options = {'name' => 'Probe status ($1)',
                                              'key_'=> 'rsm.probe.status[automatic,'.$root_servers_macros.']',
                                              'hostid' => $templateid,
                                              'applications' => [get_application_id('Probe status', $templateid)],
                                              'type' => 3, 'value_type' => 3, 'delay' => cfg_probe_status_delay,
                                              'valuemapid' => rsm_value_mappings->{'rsm_probe'}};

    create_item($options);

    $options = { 'description' => 'PROBE {HOST.NAME}: 8.3 - Probe has been disable more than {$IP.MAX.OFFLINE.MANUAL} hours ago',
                         'expression' => '{'.$template_name.':rsm.probe.status[manual].max({$IP.MAX.OFFLINE.MANUAL}h)}=0',
                        'priority' => '3',
                };

    create_trigger($options);


    $options = {'name' => 'Probe status ($1)',
                                              'key_'=> 'rsm.probe.status[manual]',
                                              'hostid' => $templateid,
                                              'applications' => [get_application_id('Probe status', $templateid)],
                                              'type' => 2, 'value_type' => 3,
                                              'valuemapid' => rsm_value_mappings->{'rsm_probe'}};

    create_item($options);

    $options = { 'description' => 'PROBE {HOST.NAME}: 8.2 - Probe has been disabled by tests',
                         'expression' => '{'.$template_name.':rsm.probe.status[automatic,"{$RSM.IP4.ROOTSERVERS1}","{$RSM.IP6.ROOTSERVERS1}"].last(0)}=0',
                        'priority' => '4',
                };

    create_trigger($options);



    return $templateid;
}

sub add_dependency($$) {
    my $triggerid = shift;
    my $depend_down = shift;

    my $result = $zabbix->trigger_dep_add({'triggerid' => $depend_down, 'dependsOnTriggerid' => $triggerid});

    return $result;
}

sub create_probe_status_host {
    my $groupid = shift;

    my $name = 'Probes Status';

    my $hostid = create_host({'groups' => [{'groupid' => $groupid}],
                                          'host' => $name,
                                          'interfaces' => [{'type' => 1, 'main' => true, 'useip' => true,
                                                            'ip'=> '127.0.0.1',
                                                            'dns' => '', 'port' => '10050'}]
                });

    my $interfaceid = $zabbix->get('hostinterface', {'hostids' => $hostid, 'output' => ['interfaceid']});

    my $options = {'name' => 'Total number of probes for DNS tests',
                                              'key_'=> 'online.nodes.pl[total,dns]',
                                              'hostid' => $hostid,
					      'interfaceid' => $interfaceid->{'interfaceid'},
                                              'applications' => [get_application_id('Probes availability', $hostid)],
                                              'type' => 10, 'value_type' => 3,
					      'delay' => 300,
                                              };
    create_item($options);

    $options = {'name' => 'Number of online probes for DNS tests',
                                              'key_'=> 'online.nodes.pl[online,dns]',
                                              'hostid' => $hostid,
					      'interfaceid' => $interfaceid->{'interfaceid'},
                                              'applications' => [get_application_id('Probes availability', $hostid)],
                                              'type' => 10, 'value_type' => 3,
					      'delay' => 60,
                                              };
    create_item($options);

    $options = {'name' => 'Total number of probes for EPP tests',
                                              'key_'=> 'online.nodes.pl[total,epp]',
                                              'hostid' => $hostid,
                                              'interfaceid' => $interfaceid->{'interfaceid'},
                                              'applications' => [get_application_id('Probes availability', $hostid)],
                                              'type' => 10, 'value_type' => 3,
                                              'delay' => 300,
                                              };
    create_item($options);

    $options = {'name' => 'Number of online probes for EPP tests',
                                              'key_'=> 'online.nodes.pl[online,epp]',
                                              'hostid' => $hostid,
                                              'interfaceid' => $interfaceid->{'interfaceid'},
                                              'applications' => [get_application_id('Probes availability', $hostid)],
                                              'type' => 10, 'value_type' => 3,
                                              'delay' => 60,
                                              };
    create_item($options);

    $options = {'name' => 'Total number of probes for RDDS tests',
                                              'key_'=> 'online.nodes.pl[total,rdds]',
                                              'hostid' => $hostid,
                                              'interfaceid' => $interfaceid->{'interfaceid'},
                                              'applications' => [get_application_id('Probes availability', $hostid)],
                                              'type' => 10, 'value_type' => 3,
                                              'delay' => 300,
                                              };
    create_item($options);

    $options = {'name' => 'Number of online probes for RDDS tests',
                                              'key_'=> 'online.nodes.pl[online,rdds]',
                                              'hostid' => $hostid,
                                              'interfaceid' => $interfaceid->{'interfaceid'},
                                              'applications' => [get_application_id('Probes availability', $hostid)],
                                              'type' => 10, 'value_type' => 3,
                                              'delay' => 60,
                                              };
    create_item($options);

    $options = {'name' => 'Total number of probes with enabled IPv4',
                                              'key_'=> 'online.nodes.pl[total,ipv4]',
                                              'hostid' => $hostid,
                                              'interfaceid' => $interfaceid->{'interfaceid'},
                                              'applications' => [get_application_id('Probes availability', $hostid)],
                                              'type' => 10, 'value_type' => 3,
                                              'delay' => 300,
                                              };
    create_item($options);

    $options = {'name' => 'Number of online probes with enabled IPv4',
                                              'key_'=> 'online.nodes.pl[online,ipv4]',
                                              'hostid' => $hostid,
                                              'interfaceid' => $interfaceid->{'interfaceid'},
                                              'applications' => [get_application_id('Probes availability', $hostid)],
                                              'type' => 10, 'value_type' => 3,
                                              'delay' => 60,
                                              };
    create_item($options);

    $options = {'name' => 'Total number of probes with enabled IPv6',
                                              'key_'=> 'online.nodes.pl[total,ipv6]',
                                              'hostid' => $hostid,
                                              'interfaceid' => $interfaceid->{'interfaceid'},
                                              'applications' => [get_application_id('Probes availability', $hostid)],
                                              'type' => 10, 'value_type' => 3,
                                              'delay' => 300,
                                              };
    create_item($options);

    $options = {'name' => 'Number of online probes with enabled IPv6',
                                              'key_'=> 'online.nodes.pl[online,ipv6]',
                                              'hostid' => $hostid,
                                              'interfaceid' => $interfaceid->{'interfaceid'},
                                              'applications' => [get_application_id('Probes availability', $hostid)],
                                              'type' => 10, 'value_type' => 3,
                                              'delay' => 60,
                                              };
    create_item($options);

    $options = { 'description' => 'DNS-PROBE: 12.2 - Online probes for test [{ITEM.LASTVALUE1}] is less than [{$RSM.DNS.PROBE.ONLINE}]',
                         'expression' => '{'.$name.':online.nodes.pl[online,dns].last(0)}<{$RSM.DNS.PROBE.ONLINE}',
                        'priority' => '5',
                };

    create_trigger($options);

    $options = { 'description' => 'RDDS-PROBE: 12.2 - Online probes for test [{ITEM.LASTVALUE1}] is less than [{$RSM.RDDS.PROBE.ONLINE}]',
                         'expression' => '{'.$name.':online.nodes.pl[online,rdds].last(0)}<{$RSM.RDDS.PROBE.ONLINE}',
                        'priority' => '5',
                };

    create_trigger($options);

    $options = { 'description' => 'EPP-PROBE: 12.2 - Online probes for test [{ITEM.LASTVALUE1}] is less than [{$RSM.EPP.PROBE.ONLINE}]',
                         'expression' => '{'.$name.':online.nodes.pl[online,epp].last(0)}<{$RSM.EPP.PROBE.ONLINE}',
                        'priority' => '5',
                };

    create_trigger($options);

    $options = { 'description' => 'IPv4-PROBE: 12.2 - Online probes with IPv4 [{ITEM.LASTVALUE1}] is less than [{$RSM.IP4.MIN.PROBE.ONLINE}]',
                         'expression' => '{'.$name.':online.nodes.pl[online,ipv4].last(0)}<{$RSM.IP4.MIN.PROBE.ONLINE}',
                        'priority' => '5',
                };

    create_trigger($options);

    $options = { 'description' => 'IPv6-PROBE: 12.2 - Online probes with IPv6 [{ITEM.LASTVALUE1}] is less than [{$RSM.IP6.MIN.PROBE.ONLINE}]',
                         'expression' => '{'.$name.':online.nodes.pl[online,ipv6].last(0)}<{$RSM.IP6.MIN.PROBE.ONLINE}',
                        'priority' => '5',
                };

    create_trigger($options);
}

sub get_items_like($$$) {
    my $hostid = shift;
    my $like = shift;
    my $is_template = shift;

    my $result;

    if (!defined($is_template) or $is_template == false) {
	$result = $zabbix->get('item', {'hostids' => [$hostid], 'output' => ['itemid', 'name', 'hostid', 'key_', 'status'], 'search' => {'key_' => $like}, 'preservekeys' => true});
	return $result;
    }

    $result = $zabbix->get('item', {'templateids' => [$hostid], 'output' => ['itemid', 'name', 'hostid', 'key_', 'status'], 'search' => {'key_' => $like}, 'preservekeys' => true});

    return $result;
}

sub get_triggers_by_items($) {
    my @itemids = shift;

    my $result;

    $result = $zabbix->get('trigger', {'itemids' => @itemids, 'output' => ['triggerid'], 'preservekeys' => true});

    return $result;
}

sub set_tld_type($$) {
	my $tld = shift;
	my $tld_type = shift;

	my %tld_type_groups = (@{[TLD_TYPE_G]} => undef, @{[TLD_TYPE_CC]} => undef, @{[TLD_TYPE_OTHER]} => undef, @{[TLD_TYPE_TEST]} => undef);

	foreach my $group (keys(%tld_type_groups))
	{
		my $groupid = create_group($group);

		pfail($groupid->{'data'}) if (check_api_error($groupid) == true);

		$tld_type_groups{$group} = int($groupid);
	}

	my $result = get_host($tld, true);

	pfail($result->{'data'}) if (check_api_error($result) == true);

	pfail("host \"$tld\" not found") unless ($result->{'hostid'});

	my $hostid = $result->{'hostid'};
	my $hostgroups_ref = $result->{'groups'};
	my $current_tld_type;
	my $alreadyset = false;

	my $options = {'hostid' => $hostid, 'host' => $tld, 'groups' => []};

	foreach my $hostgroup_ref (@$hostgroups_ref)
	{
		my $hostgroupname = $hostgroup_ref->{'name'};
		my $hostgroupid = $hostgroup_ref->{'groupid'};

		my $skip_hostgroup = false;

		foreach my $group (keys(%tld_type_groups))
		{
			my $groupid = $tld_type_groups{$group};

			if ($hostgroupid == $groupid)
			{
				if ($tld_type eq $hostgroupname)
				{
					$alreadyset = true;
				}

				pfail("TLD \"$tld\" linked to more than one TLD type") if (defined($current_tld_type));

				$current_tld_type = $hostgroupid;
				$skip_hostgroup = true;

				last;
			}
		}

		push(@{$options->{'groups'}}, {'groupid' => $hostgroupid}) if ($skip_hostgroup == false);
	}

	return false if ($alreadyset == true);

	# add new group to the options
	push(@{$options->{'groups'}}, {'groupid' => $tld_type_groups{$tld_type}});

	$result = create_host($options);

	pfail($result->{'data'}) if (check_api_error($result) == true);

	return true;
}

sub create_cron_jobs($) {
    my $slv_path = shift;

    my $errlog = '/var/log/zabbix/rsm.slv.err';

    my $slv_file;

    my $rv = opendir DIR, "/etc/cron.d";

    pfail("cannot open /etc/cron.d") unless ($rv);

    # first remove current entries
    while (($slv_file = readdir DIR)) {
	next unless ($slv_file =~ /^rsm\.slv\..*\.pl$/);

	$slv_file = "/etc/cron.d/$slv_file";

	system("/bin/rm -f $slv_file");
    }

    my $avail_shift = 30;
    my $avail_step = 5;
    my $avail_limit = 60;
    my $rollweek_shift = 0;
    my $rollweek_step = 5;
    my $rollweek_limit = 30;

    my $avail_cur = $avail_shift;
    my $rollweek_cur = $rollweek_shift;
    
    $rv = opendir DIR, $slv_path;

    pfail("cannot open $slv_path") unless ($rv);

    # set up what's needed
    while (($slv_file = readdir DIR)) {
	next unless ($slv_file =~ /^rsm\.slv\..*\.pl$/);

	if ($slv_file =~ /\.slv\..*\.month\.pl$/) {
	    # monthly data once a day
	    system("echo '0 0 * * * root $slv_path/$slv_file >> $errlog 2>&1' > /etc/cron.d/$slv_file");
	} elsif ($slv_file =~ /\.slv\.dns\.downtime\.pl$/) {
	    # DNS downtime every minute
	    system("echo '* * * * * root $slv_path/$slv_file >> $errlog 2>&1' > /etc/cron.d/$slv_file");
	} elsif ($slv_file =~ /\.slv\..*\.avail\.pl$/) {
	    # separate rollweek and avail by some delay
	    system("echo '* * * * * root sleep $avail_cur; $slv_path/$slv_file >> $errlog 2>&1' > /etc/cron.d/$slv_file");
	    $avail_cur += $avail_step;
	    $avail_cur = $avail_shift if ($avail_cur >= $avail_limit);
	} else {
	    system("echo '* * * * * root sleep $rollweek_cur; $slv_path/$slv_file >> $errlog 2>&1' > /etc/cron.d/$slv_file");
	    $rollweek_cur += $rollweek_step;
	    $rollweek_cur = $rollweek_shift if ($rollweek_cur >= $rollweek_limit);
	}
    }
}

sub pfail {
    print("Error: ", @_, "\n");
    exit -1;
}

1;
