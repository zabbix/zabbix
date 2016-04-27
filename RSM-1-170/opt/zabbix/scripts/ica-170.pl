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

parse_opts();

setopt('nolog');

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

print("Fixing value mappings...\n");
db_exec("update valuemaps set name='RSM Service Availability' where valuemapid=16");
my $rows_ref = db_select("select mappingid from mappings where mappingid=113");
if (scalar(@{$rows_ref}) == 0)
{
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (113,16,'2','Up (inconclusive)')");
}
db_exec("update valuemaps set name='RSM RDDS probe result' where valuemapid=18");
db_exec("update valuemaps set name='RSM EPP result' where valuemapid=19");
db_exec("update items set valuemapid=null where key_='" . 'rsm.dns.udp[{$RSM.TLD}]' . "'");
db_exec("update items set valuemapid=null where key_='" . 'rsm.epp[{$RSM.TLD},"{$RSM.EPP.SERVERS}"]' . "'");

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

my $slv_items_to_remove_like =
[
	'rsm.slv.dns.ns.results[%',
	'rsm.slv.dns.ns.positive[%',
	'rsm.slv.dns.ns.sla[%',
	'rsm.slv.%.month%'
];

my $trigger_names_to_rename =
{
	'PROBE {HOST.NAME}: 8.3 - Probe has been disable more than {$IP.MAX.OFFLINE.MANUAL} hours ago' => 'PROBE {HOST.NAME}: 8.3 - Probe has been disabled for over {$IP.MAX.OFFLINE.MANUAL} hours'
};

my $item_keys_to_rename =
{
	'rsm.slv.dns.upd' => 'rsm.slv.dns.udp.upd'
};

my $item_names_to_rename =
{
	'EPP service availability at $1 ($2)' => 'EPP test',
	'Number of working DNS Name Servers of $1 (UDP)' => 'DNS UDP test',
	'Number of working DNS Name Servers of $1 (TCP)' => 'DNS TCP test',
	'RDDS availability of $1' => 'RDDS test'
};

print("Renaming triggers...\n");
foreach my $from (keys(%{$trigger_names_to_rename}))
{
	my $to = $trigger_names_to_rename->{$from};

	db_exec("update triggers set description='$to' where description='$from'");
}

print("Renaming item keys...\n");
foreach my $from (keys(%{$item_keys_to_rename}))
{
	my $to = $item_keys_to_rename->{$from};

	db_exec("update items set key_='$to' where key_='$from'");
}

print("Renaming item names...\n");
foreach my $from (keys(%{$item_names_to_rename}))
{
	my $to = $item_names_to_rename->{$from};

	db_exec("update items set name='$to' where name='$from'");
}

print("Deleting obsoleted items...\n");
foreach my $key (@{$slv_items_to_remove_like})
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

		my $applicationid = __get_applicationid($templateid, $application);

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

print("Creating SLV items...\n");
__create_missing_slv_montly_items();

print("Fixing applications...\n");
my $name_from = 'SLV current month';
my $name_to = 'SLV monthly';
foreach (@{$tlds_ref})
{
	$tld = $_;	# set globally

	my $rows_ref = db_select(
		"select a.applicationid".
		" from applications a,hosts h".
		" where a.hostid=h.hostid".
			" and h.host='$tld'".
			" and a.name='$name_from'");

	next if (scalar(@{$rows_ref}) == 0);

	print("  $tld\n");

	my $applicationid_from = $rows_ref->[0]->[0];

	$rows_ref = db_select(
		"select a.applicationid".
		" from applications a,hosts h".
		" where a.hostid=h.hostid".
			" and h.host='$tld'".
			" and a.name='$name_to'");

	my $applicationid_to = $rows_ref->[0]->[0];

	db_exec("update items_applications".
		" set applicationid=$applicationid_to".
		" where applicationid=$applicationid_from");

	db_exec("delete from applications".
		" where applicationid=$applicationid_from");
}
print("Done!\n");

sub __create_item
{
	my $options = shift;

	my $result;

	if ($zabbix->exist('item', {'hostid' => $options->{'hostid'}, 'key_' => $options->{'key_'}}))
	{
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
	else
	{
		$result = $zabbix->create('item', $options);
	}

	pfail($zabbix->last_error) if (defined($zabbix->last_error));

	$result = ${$result->{'itemids'}}[0] if (defined(${$result->{'itemids'}}[0]));

	return $result;
}

sub __create_slv_item
{
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
	elsif ($value_type == VALUE_TYPE_PERC)
	{
		$options = {'name' => $name,
			    'key_'=> $key,
			    'hostid' => $hostid,
			    'type' => 2, 'value_type' => 0,
			    'applications' => $applicationids,
			    'status' => ITEM_STATUS_ACTIVE,
			    'units' => '%'};
	}
	elsif ($value_type == VALUE_TYPE_DOUBLE)
	{
		$options = {'name' => $name,
			    'key_'=> $key,
			    'hostid' => $hostid,
			    'type' => 2, 'value_type' => 0,
			    'applications' => $applicationids,
			    'status' => ITEM_STATUS_ACTIVE};
	}
	else
	{
		pfail("Unknown value type $value_type.");
	}

	return __create_item($options);
}

sub __get_applicationid
{
	my $hostid = shift;
	my $application = shift;

	my $result = $zabbix->get('application', {'hostids' => [$hostid], 'filter' => {'name' => $application}});
	pfail("cannot get application ID of \"$application\": ", Dumper($zabbix->last_error)) if (defined($zabbix->last_error));
	my $applicationid = $result->{'applicationid'};
	pfail("cannot get application ID of \"$application\"") unless (defined($applicationid));

	return $applicationid;
}

sub __create_slv_monthly($$$)
{
	my $test_name = shift;
	my $key_base = shift;
	my $hostid = shift;

	my $applicationid = __get_applicationid($hostid, APP_SLV_MONTHLY);

	unless ($zabbix->exist('item', {'hostid' => $hostid, 'key_' => $key_base . '.pfailed'}))
	{
		__create_slv_item($test_name . ': % of failed tests',   $key_base . '.pfailed', $hostid, VALUE_TYPE_PERC,  [$applicationid]);
	}
	unless ($zabbix->exist('item', {'hostid' => $hostid, 'key_' => $key_base . '.failed'}))
	{
		__create_slv_item($test_name . ': # of failed tests',   $key_base . '.failed',  $hostid, VALUE_TYPE_NUM,   [$applicationid]);
	}
	unless ($zabbix->exist('item', {'hostid' => $hostid, 'key_' => $key_base . '.max'}))
	{
		__create_slv_item($test_name . ': expected # of tests', $key_base . '.max',     $hostid, VALUE_TYPE_NUM,   [$applicationid]);
	}
	unless ($zabbix->exist('item', {'hostid' => $hostid, 'key_' => $key_base . '.avg'}))
	{
		__create_slv_item($test_name . ': average result',      $key_base . '.avg',     $hostid, VALUE_TYPE_DOUBLE, [$applicationid]);
	}
}

sub __get_host_macro($$)
{
	my $hostid = shift;
	my $m = shift;

	my $rows_ref = db_select("select value from hostmacro where hostid=$hostid and macro='$m'");

	fail("cannot find macro '$m'") unless (1 == scalar(@$rows_ref));

	return $rows_ref->[0]->[0];
}

sub __create_missing_slv_montly_items
{
	foreach (@{$tlds_ref})
	{
		$tld = $_;	# set globally

		print("  $tld\n");

		my $rows_ref = db_select("select hostid from hosts where host='$tld'");

		pfail("cannot find TLD \"$tld\" in the database") unless (scalar(@{$rows_ref}) == 1);

		my $hostid = $rows_ref->[0]->[0];

		$rows_ref = db_select("select hostid from hosts where host='Template $tld'");

		pfail("cannot find TLD \"$tld\" in the database") unless (scalar(@{$rows_ref}) == 1);

		my $templateid = $rows_ref->[0]->[0];

		my $rdds_enabled = __get_host_macro($templateid, '{$RSM.TLD.RDDS.ENABLED}');
		my $epp_enabled = __get_host_macro($templateid, '{$RSM.TLD.EPP.ENABLED}');

		__create_slv_monthly("DNS UDP Resolution RTT", "rsm.slv.dns.udp.rtt", $hostid);
		__create_slv_monthly("DNS TCP Resolution RTT", "rsm.slv.dns.tcp.rtt", $hostid);

		if ($rdds_enabled == 1)
		{
			__create_slv_monthly("RDDS43 Query RTT", "rsm.slv.rdds43.rtt", $hostid);
			__create_slv_monthly("RDDS43 Query RTT", "rsm.slv.rdds80.rtt", $hostid);
		}

		if ($epp_enabled == 1)
		{
			__create_slv_monthly("DNS update time", "rsm.slv.dns.upd", $hostid);

			if ($rdds_enabled == 1)
			{
				__create_slv_monthly("RDDS update time", "rsm.slv.rdds.upd", $hostid);
			}

			__create_slv_monthly('EPP Session-Command RTT',   'rsm.slv.epp.login', $hostid);
			__create_slv_monthly('EPP Transform-Command RTT', 'rsm.slv.epp.update', $hostid);
			__create_slv_monthly('EPP Transform-Command RTT', 'rsm.slv.epp.update', $hostid);
		}
	}
}
