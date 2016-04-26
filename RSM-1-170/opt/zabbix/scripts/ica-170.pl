#!/usr/bin/perl

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
use RSMSLV;
use TLD_constants qw(:general :templates :value_types :ec :rsm :slv :config :api);
use TLDs;

my $config = get_rsm_config();

my $zabbix = Zabbix->new(
	{
		'url' => $config->{'zapi'}->{'url'},
		'user' => $config->{'zapi'}->{'user'},
		'password' => $config->{'zapi'}->{'password'}
	});

if (defined($zabbix->{'error'}) && $zabbix->{'error'} ne '')
{
	pfail("cannot connect to Zabbix API. ", $zabbix->{'error'}, "\n");
}

set_slv_config($config);
db_connect();

my $tlds_ref = get_tlds();

print("Deleting obsoleted triggers...\n");
foreach (@{$tlds_ref})
{
	$tld = $_;	# set globally

	my $host = "Template $tld";

	print("  $tld\n");

	my @triggerids;
	my $result = $zabbix->get('trigger', {'filter' => {'host' => $host}, 'output' => ['triggerid', 'description']});

	if (ref($result) eq 'ARRAY')
	{
		foreach my $r (@{$result})
		{
			push(@triggerids, $r->{'triggerid'});
			#printf("%30s | %10s | %s\n", $host, $r->{'triggerid'}, $r->{'description'});
		}
	}

	if (scalar(@triggerids) != 0)
	{
		my $result = $zabbix->remove('trigger', \@triggerids);
		pfail("cannot delete triggers by ID ", join(',', @triggerids), ": ", Dumper($zabbix->last_error())) unless ($result);
	}
}

my $slv_items_to_remove =
[
	'rsm.slv.dns.ns.results[%',
	'rsm.slv.dns.ns.positive[%',
	'rsm.slv.dns.ns.sla[%',
	'rsm.slv.%.month%'
];

my $triggers_to_rename =
{
	'PROBE {HOST.NAME}: 8.3 - Probe has been disable more than {$IP.MAX.OFFLINE.MANUAL} hours ago' => 'PROBE {HOST.NAME}: 8.3 - Probe has been disabled for over {$IP.MAX.OFFLINE.MANUAL} hours'
};

print("Renaming triggers...\n");
foreach my $from (keys(%{$triggers_to_rename}))
{
	my $to = $triggers_to_rename->{$from};

	db_exec("update triggers set description='$to' where description='$from'");
}

print("Deleting obsoleted items...\n");
foreach my $key (@{$slv_items_to_remove})
{
	db_exec("delete from items where key_ like '$key'");
}

{
	my $host = 'Template Proxy Health';
	my $item = 'rsm.probe.online';
	my $name = 'Probe main status';
	my $application = 'Probe Availability';

	my $result = $zabbix->get('template', {'filter' => {'host' => $host}});
	pfail("cannot find template \"$host\": ", Dumper($zabbix->last_error)) if (defined($zabbix->last_error));
	my $templateid = $result->{'templateid'};
	unless ($zabbix->exist('item', {'hostid' => $templateid, 'key_' => $item}))
	{
		print("Creating probe mon items...\n");

		my $result = $zabbix->get('application', {'hostids' => [$templateid], 'filter' => {'name' => $application}});
		pfail("cannot get application ID of \"$application\": ", Dumper($zabbix->last_error)) if (defined($zabbix->last_error));
		my $applicationid = $result->{'applicationid'};
		pfail("cannot get application ID of \"$application\"") unless (defined($applicationid));

		my $options = {'name' => $name,
			       'key_'=> $item,
			       'hostid' => $templateid,
			       'applications' => [$applicationid],
			       'type' => 2, 'value_type' => 3,
			       'valuemapid' => rsm_value_mappings->{'rsm_probe'}};
		$zabbix->create('item', $options);
		pfail("cannot create item for probe main status: ", Dumper($zabbix->last_error)) if (defined($zabbix->last_error));
	}
}
print("Done!\n");
