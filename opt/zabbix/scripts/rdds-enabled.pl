#!/usr/bin/perl

use lib '/opt/zabbix/scripts';

use strict;
use warnings;
use Zabbix;
use DNSTest;
use Data::Dumper;

my $macro = '{$DNSTEST.RDDS.ENABLED}';

my $config = get_dnstest_config();
my $zabbix = Zabbix->new({'url' => $config->{'zapi'}->{'url'}, user => $config->{'zapi'}->{'user'}, password => $config->{'zapi'}->{'password'}});

my $result = $zabbix->get('proxy',{'output' => ['proxyid', 'host'], 'selectInterfaces' => ['ip'], 'preservekeys' => 1 });
my @proxy_hosts;
foreach my $k (keys %$result)
{
    push(@proxy_hosts, $result->{$k}->{'host'});
}

my $total = scalar(@proxy_hosts);
my $num = 0;
foreach my $ph (@proxy_hosts)
{
    my $tname = 'Template '.$ph;
    my $result = $zabbix->get('template', {'output' => ['host'], 'filter' => {'host' => $tname}});

    my $hostid = $result->{'hostid'};

    $result = $zabbix->get('usermacro', {'output' => 'extend', 'hostids' => $hostid, 'filter' => {'macro' => $macro}});

    next unless (defined($result->{'value'}) and $result->{'value'} != 0);

    $num++;
    print("- $tname ($hostid)\n");
}
print("$num out of $total proxies have RDDS enabled\n");
