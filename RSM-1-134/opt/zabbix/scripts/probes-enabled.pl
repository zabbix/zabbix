#!/usr/bin/perl

use lib '/opt/zabbix/scripts';

use strict;
use warnings;
use Zabbix;
use RSM;
use Data::Dumper;

my $config = get_rsm_config();
my $zabbix = Zabbix->new({'url' => $config->{'zapi'}->{'url'}, user => $config->{'zapi'}->{'user'}, password => $config->{'zapi'}->{'password'}});

my $result = $zabbix->get('proxy',{'output' => ['proxyid', 'host'], 'selectInterfaces' => ['ip'], 'preservekeys' => 1 });
my @proxy_hosts;
foreach my $k (keys %$result)
{
	push(@proxy_hosts, $result->{$k}->{'host'});
}

my $total = scalar(@proxy_hosts);
my $rdds_num = 0;
my $epp_num = 0;
foreach my $ph (@proxy_hosts)
{
	my ($tname, $result, $hostid, $macro);

	my $rdds = "no";
	my $epp = "no";

	$tname = 'Template '.$ph;
	$result = $zabbix->get('template', {'output' => ['host'], 'filter' => {'host' => $tname}});

	$hostid = $result->{'hostid'};

	$macro = '{$RSM.RDDS.ENABLED}';
	$result = $zabbix->get('usermacro', {'output' => 'extend', 'hostids' => $hostid, 'filter' => {'macro' => $macro}});
	if (defined($result->{'value'}) and $result->{'value'} != 0)
	{
		$rdds_num++;
		$rdds = "yes";
	}

	$macro = '{$RSM.EPP.ENABLED}';
	$result = $zabbix->get('usermacro', {'output' => 'extend', 'hostids' => $hostid, 'filter' => {'macro' => $macro}});
	if (defined($result->{'value'}) and $result->{'value'} != 0)
	{
		$epp_num++;
		$epp = "yes";
	}

	print("  $ph ($hostid): RDDS:$rdds EPP:$epp\n");
}

if ($total == $rdds_num and $total == $epp_num)
{
	print("Total $total proxies, all with RDDS and EPP enabled\n");
}
else
{
	print("Total $total proxies, $rdds_num with RDDS enabled, $epp_num with EPP enabled\n");
}
