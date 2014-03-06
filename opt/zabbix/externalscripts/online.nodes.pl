#!/usr/bin/perl
#

use lib '/opt/zabbix/scripts';

use strict;
use Zabbix;
use DNSTest;
use Data::Dumper;

my $command = shift || 'total';

my $type = shift || 'dns';

my $hosts;

my $config = get_dnstest_config();
my $zabbix = Zabbix->new({ url => $config->{'zapi'}->{'url'}, user => $config->{'zapi'}->{'user'}, password => $config->{'zapi'}->{'password'} });

my $groupid = $zabbix->get('hostgroup', {'filter' => {'name' => ['Probes']} });

$groupid = $groupid->{'groupid'};

my $hosts_tmp = $zabbix->get('host', {'groupids' => $groupid, 'selectParentTemplates' => ['hostid'], 'preservekeys' => 1 });

foreach my $hostid (keys %{$hosts_tmp}) {
    my $host = $hosts_tmp->{$hostid};
    my @templates_tmp = $host->{'parentTemplates'};
    my @templates;

    $hosts->{$hostid} = {};

    $hosts->{$hostid}->{'status'} = 1;

    push(@templates, $hostid);

    foreach my $template (@templates_tmp) {
	push(@templates, ${$template}[0]->{'templateid'});
	@templates = (@templates, get_parent_templateids(${$template}[0]->{'templateid'}));
    }

    my $macros = $zabbix->get('usermacro', {'hostids' => [@templates], 'output' => 'extend'});

    foreach my $macro (@{$macros}) {
	my $name = $macro->{'macro'};
	my $value = $macro->{'value'};
	$hosts->{$hostid}->{$name} = $value;
    }
}

my $items = {};

$items = $zabbix->get('item', {'groupids' => $groupid, 'output'=> ['itemid', 'hostid', 'key_', 'lastvalue'], 'preservekeys' => 1, 'filter' => {'key_' => ['dnstest.probe.status[manual]', 'dnstest.probe.status[automatic,"{$DNSTEST.IP4.ROOTSERVERS1}","{$DNSTEST.IP6.ROOTSERVERS1}"]']}});

foreach my $itemid (sort keys %{$items}) {
    my $hostid = $items->{$itemid}->{'hostid'};
    my $value = $items->{$itemid}->{'lastvalue'} || '1';
    my $key = $items->{$itemid}->{'key_'};

    $hosts->{$hostid}->{'status'} = 0 if $value == 0;
}

my $total = 0;
my $online = 0;

foreach my $hostid (keys %{$hosts}) {
    my $status = $hosts->{$hostid}->{'status'};
    
    if ($type eq 'dns') {
	$online++ if $status == 1;
	$total++;
    }
    elsif ($type eq 'epp') {
	$online++ if $status == 1 and $hosts->{$hostid}->{'{$DNSTEST.EPP.ENABLED}'} == 1;
	$total++ if $hosts->{$hostid}->{'{$DNSTEST.EPP.ENABLED}'} == 1;
    }
    elsif ($type eq 'rdds') {
	$online++ if $status == 1 and $hosts->{$hostid}->{'{$DNSTEST.RDDS.ENABLED}'} == 1;
	$total++ if $hosts->{$hostid}->{'{$DNSTEST.RDDS.ENABLED}'} == 1;
    }
    elsif ($type eq 'ipv4') {
        $online++ if $status == 1 and $hosts->{$hostid}->{'{$DNSTEST.IP4.ENABLED}'} == 1;
        $total++ if $hosts->{$hostid}->{'{$DNSTEST.IP4.ENABLED}'} == 1;
    }
    elsif ($type eq 'ipv6') {
        $online++ if $status == 1 and $hosts->{$hostid}->{'{$DNSTEST.IP6.ENABLED}'} == 1;
        $total++ if $hosts->{$hostid}->{'{$DNSTEST.IP6.ENABLED}'} == 1;
    }
}

print $total if $command eq 'total';
print $online if $command eq 'online';
print 0 if $command ne 'total' and $command ne 'online';

exit;

sub get_parent_templateids($) {
    my $templateid = shift;
    my @result;

    my $hosts = $zabbix->get('template', {'templateids' => $templateid, 'selectParentTemplates' => ['templateid']});

    my @templates = $hosts->{'parentTemplates'} if scalar @{$hosts->{'parentTemplates'}};

    foreach my $template (@templates) {
	my $templateid = ${$template}[0]->{'templateid'};
	push (@result, $templateid);
	my @parent_templates = get_parent_templateids($templateid);
	@result = (@result, @parent_templates) if scalar @parent_templates;
    }

    return @result;
}
