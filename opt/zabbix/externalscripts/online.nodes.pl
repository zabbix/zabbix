#!/usr/bin/perl
#

use lib '/opt/zabbix/scripts';

use strict;
use Zabbix;
use DNSTest;
use Data::Dumper;

my $command = shift;

my $add_param = shift;

my $config = get_dnstest_config();
my $zabbix = Zabbix->new({ url => $config->{'zapi'}->{'url'}, user => $config->{'zapi'}->{'user'}, password => $config->{'zapi'}->{'password'} });

my $groupid = $zabbix->get('hostgroup', {'filter' => {'name' => ['Probes']} });

$groupid = $groupid->{'groupid'};

if (defined($command) and $command eq 'total') {
    my $total = $zabbix->get('host', {'groupids' => $groupid, 'countOutput' => '1' });

    print $total;
    exit;
}

my $items = {};

$items = $zabbix->get('item', {'groupids' => $groupid, 'output'=> ['itemid', 'hostid', 'key_', 'lastvalue'], 'preservekeys' => 1, 'filter' => {'key_' => ['dnstest.probe.status[manual]', 'dnstest.probe.status[automatic,"{$DNSTEST.IP4.ROOTSERVERS1}","{$DNSTEST.IP6.ROOTSERVERS1}"]']}});

my %acc_status;

foreach my $itemid (sort keys %{$items}) {
    my $hostid = $items->{$itemid}->{'hostid'};
    my $value = $items->{$itemid}->{'lastvalue'} || '1';
    my $key = $items->{$itemid}->{'key_'};

    $acc_status{$hostid} = 0 if defined($acc_status{$hostid}) and $value == 0 and $acc_status{$hostid} != 0;

    $acc_status{$hostid} = $value unless defined($acc_status{$hostid});
}

my $result = scalar(keys %{$items}) / 2;

foreach my $hostid (keys %acc_status) {
    $result-- if $acc_status{$hostid} == 0;
}

print $result;
