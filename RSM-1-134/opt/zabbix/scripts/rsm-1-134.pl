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

my $rows_ref;
my $dns_valuemapid = 20;
my $epp_valuemapid = 21;

info("fixing items...");
db_exec("update items set name='DNS test',key_='rsm.dns[{\$RSM.TLD}]' where key_='rsm.dns.udp[{\$RSM.TLD}]'");
db_exec("delete from items where key_='rsm.dns.tcp[{\$RSM.TLD}]'");
$rows_ref = db_select("select 1 from applications where name='DNS' limit 1");
if (0 == @{$rows_ref})
{
	info("fixing applications...");
	db_exec("update applications set name='DNS' where name='DNS (UDP)'");
	db_exec("delete from applications where name='DNS (TCP)'");
	db_exec("update applications set name='DNS (UDP)' where name='DNS RTT (UDP)'");
	db_exec("update applications set name='DNS (TCP)' where name='DNS RTT (TCP)'");
}

info("fixing global macros...");
db_exec("update globalmacro set macro='{\$RSM.DNS.DELAY}' where macro='{\$RSM.DNS.UDP.DELAY}'");
db_exec("delete from globalmacro where macro='{\$RSM.DNS.TCP.DELAY}'");

my $tlds_ref = get_tlds('epp');
my @items_to_create;
my @items_to_update;
my @hostids;
my $applications;
foreach (@{$tlds_ref})
{
	$tld = $_;

	push(@hostids, get_hostid("Template $tld"));
}
undef($tld);

my $items_ref = get_items_by_hostids(\@hostids, 'rsm.dns.udp.upd[', 0, 1);	# incomplete key, with keys

foreach my $item_ref (@{$items_ref})
{
	my $proto_uc = 'UDP';
	my $proto_lc = lc($proto_uc);

	my $templateid = $item_ref->{'hostid'};
	my $itemid = $item_ref->{'itemid'};
	my $key = $item_ref->{'key'};

	my $nsip = get_nsip_from_key($key);

	unless (exists($applications->{$templateid}->{'DNS ('.$proto_uc.')'})) {
		$applications->{$templateid}->{'DNS ('.$proto_uc.')'} = __get_application_id($templateid, 'DNS ('.$proto_uc.')');
	}

	my $name = 'DNS update time of $2 ($3) ('.$proto_uc.')';

	$rows_ref = db_select("select name from items where itemid=$itemid");

	last if ($rows_ref->[0]->[0] eq $name);

	my $options = {'itemid' => $itemid,
		       'name' => 'DNS update time of $2 ($3) ('.$proto_uc.')'};
	push(@items_to_update, $options);

	$proto_uc = 'TCP';
	$proto_lc = lc($proto_uc);

	unless (exists($applications->{$templateid}->{'DNS ('.$proto_uc.')'})) {
		$applications->{$templateid}->{'DNS ('.$proto_uc.')'} = __get_application_id($templateid, 'DNS ('.$proto_uc.')');
	}

	$options = {'name' => $name,
		       'key_'=> 'rsm.dns.'.$proto_lc.'.upd[{$RSM.TLD},'.$nsip.']',
		       'hostid' => $templateid,
		       'applications' => [$applications->{$templateid}->{'DNS ('.$proto_uc.')'}],
		       'type' => 2, 'value_type' => 0,
		       'valuemapid' => rsm_value_mappings->{'dns_test'},
		       'status' => 0};
	push(@items_to_create, $options);
}

if (scalar(@items_to_update) != 0)
{
	info("updating \"DNS UDP Update Time\" items...");
	__update_items(\@items_to_update);
}

if (scalar(@items_to_create) != 0)
{
	info("creating \"DNS TCP Update Time\" items...");
	__create_items(\@items_to_create);
}

$rows_ref = db_select("select 1 from mappings where newvalue='Down (UDP:down, TCP:down)'");
if (scalar(@{$rows_ref}) == 0)
{
	info("fixing value map names...");
	db_exec("update valuemaps set name='DNS Test' where valuemapid=13");
	db_exec("update valuemaps set name='RDDS Test' where valuemapid=15");
	db_exec("update valuemaps set name='EPP Test' where valuemapid=19");
	db_exec("update valuemaps set name='RDDS Test result' where valuemapid=18");
	db_exec("update valuemaps set name='Service availability' where valuemapid=16");
	db_exec("update valuemaps set name='Probe status' where valuemapid=14");
	db_exec("insert into valuemaps (valuemapid,name) values ($dns_valuemapid,'DNS Test result')");
	my $mappingid = 273;
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_valuemapid,'2','Up (UDP:up)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_valuemapid,'3','Up (TCP:up)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_valuemapid,'4','Up (UDP:up, TCP:up)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_valuemapid,'5','Down (UDP:down)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_valuemapid,'6','Down (TCP:down)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_valuemapid,'7','Down (UDP:up, TCP:down)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_valuemapid,'8','Down (UDP:down, TCP:up)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_valuemapid,'9','Down (UDP:down, TCP:down)')");

	db_exec("insert into valuemaps (valuemapid,name) values ($epp_valuemapid,'EPP Test result')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$epp_valuemapid,'0','Down')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$epp_valuemapid,'1','Up')");
}

$rows_ref = db_select("select itemid from items where key_='rsm.dns[{\$RSM.TLD}]' and templateid is null");
my @dns_items_to_update;
foreach my $row_ref (@{$rows_ref})
{
	my $options =
	{
		'itemid' => $row_ref->[0],
		'valuemapid' => $dns_valuemapid
	};
	push(@dns_items_to_update, $options);
}
if (scalar(@dns_items_to_update) != 0)
{
	info("setting value map for DNS Test items...");
	__update_items(\@dns_items_to_update);
}
$rows_ref = db_select("select itemid from items where key_ like 'rsm.epp[{\$RSM.TLD}%]' and templateid is null");
my @epp_items_to_update;
foreach my $row_ref (@{$rows_ref})
{
	my $options =
	{
		'itemid' => $row_ref->[0],
		'valuemapid' => $epp_valuemapid
	};
	push(@epp_items_to_update, $options);
}
if (scalar(@epp_items_to_update) != 0)
{
	info("setting value map for EPP Test items...");
	__update_items(\@epp_items_to_update);
}
info("done!");

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

sub __create_items
{
	my $options = shift;

	my $result = $zabbix->create('item', $options);

	pfail(Dumper($zabbix->last_error)) if (defined($zabbix->last_error));

	return $result->{'itemids'};
}

sub __update_items
{
	my $options = shift;

	my $result = $zabbix->update('item', $options);

	pfail(Dumper($zabbix->last_error)) if (defined($zabbix->last_error));

	return $result->{'itemids'};
}

sub __create_trigger
{
	my $options = shift;
	my $is_new = shift;

	my $result;

	$is_new = false unless defined $is_new;

	if ($is_new eq false)
	{
		if ($zabbix->exist('trigger', {'expression' => $options->{'expression'}}))
		{
			#$result = $zabbix->update('trigger', $options);
		}
		else
		{
			$result = $zabbix->create('trigger', $options);
		}
	}
	else
	{
		$result = $zabbix->create('trigger', $options);
	}

	#    pfail("cannot create trigger:\n", Dumper($options)) if (ref($result) ne '' or $result eq '');

	return $result;
}

sub __delete_triggers
{
	my $triggerids = shift;

	return unless $triggerids && scalar(@{$triggerids});

	return $zabbix->remove('trigger', $triggerids);
}

sub __create_macro
{
	my $name = shift;
	my $value = shift;
	my $templateid = shift;
	my $force_update = shift;
	my $is_new = shift;

	my $macroid;

	my $result;

	$is_new = false unless defined $is_new;

	if (defined($templateid))
	{
		if ($is_new eq false)
		{
			$result = $zabbix->get('usermacro',{'output' => 'extend', 'hostids' => $templateid, 'filter' => {'macro' => $name}});
		}

		if (exists($result->{'hostmacroid'}))
		{
			$macroid = $result->{'hostmacroid'};
			my $zbx_value = $result->{'value'};

			if ($value ne $zbx_value)
			{
				$zabbix->update('usermacro',{'hostmacroid' => $macroid, 'value' => $value}) if defined($force_update);
			}
		}
		else
		{
			$result = $zabbix->create('usermacro',{'hostid' => $templateid, 'macro' => $name, 'value' => $value});
			$macroid = pop(@{$result->{'hostmacroids'}});
		}
	}
	else
	{
		if ($is_new eq false)
		{
			$result = $zabbix->get('usermacro',{'output' => 'extend', 'globalmacro' => 1, 'filter' => {'macro' => $name}} );
		}

		if (exists($result->{'globalmacroid'}))
		{
			$macroid = $result->{'globalmacroid'};
			my $zbx_value = $result->{'value'};

			if ($value ne $zbx_value)
			{
				$zabbix->macro_global_update({'globalmacroid' => $macroid, 'value' => $value}) if defined($force_update);
			}
		}
		else
		{
			$result = $zabbix->macro_global_create({'macro' => $name, 'value' => $value});
			$macroid = pop(@{$result->{'globalmacroids'}});
		}
	}

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

sub __get_application_id
{
	my $hostid = shift;
	my $application = shift;

	my $result = $zabbix->get('application', {'hostids' => [$hostid], 'filter' => {'name' => $application}});
	pfail("cannot get application ID of \"$application\": ", Dumper($zabbix->last_error)) if (defined($zabbix->last_error));
	my $applicationid = $result->{'applicationid'};
	pfail("cannot get application ID of \"$application\"") unless (defined($applicationid));

	return $applicationid;
}

sub __create_slv_monthly($$$$$)
{
	my $test_name = shift;
	my $key_base = shift;
	my $hostid = shift;
	my $host_name = shift;
	my $macro = shift;

	my $applicationid = __get_application_id($hostid, APP_SLV_MONTHLY);

	unless ($zabbix->exist('item', {'hostid' => $hostid, 'key_' => $key_base . '.pfailed'}))
	{
		__create_slv_item($test_name . ': % of failed tests',   $key_base . '.pfailed', $hostid, VALUE_TYPE_PERC,  [$applicationid]);
		__create_slv_item($test_name . ': # of failed tests',   $key_base . '.failed',  $hostid, VALUE_TYPE_NUM,   [$applicationid]);
		__create_slv_item($test_name . ': expected # of tests', $key_base . '.max',     $hostid, VALUE_TYPE_NUM,   [$applicationid]);
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

sub __create_missing_slv_montly_items_and_triggers
{
	my $tlds_ref = shift;

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

		__create_slv_monthly("DNS UDP Resolution RTT", "rsm.slv.dns.udp.rtt", $hostid, $tld, '{$RSM.SLV.DNS.UDP.RTT}');
		__create_slv_monthly("DNS TCP Resolution RTT", "rsm.slv.dns.tcp.rtt", $hostid, $tld, '{$RSM.SLV.DNS.TCP.RTT}');

		# create Service downtime triggers
		my @services = ('dns');
		push(@services, 'rdds') if ($rdds_enabled == 1);
		push(@services, 'epp') if ($epp_enabled == 1);

		foreach my $service (@services)
		{
			my $item_key = 'rsm.slv.'.$service.'.downtime';

			my $options = {
				'description' => uc($service).' has been down for over {$RSM.SLV.'.uc($service).'.AVAIL} minutes',
				'expression' => '{'.$tld.':'.$item_key.'.last(0)}>{$RSM.SLV.'.uc($service).'.AVAIL}',
				'priority' => '4'
			};

			__create_trigger($options);
		}

		if ($rdds_enabled == 1)
		{
			__create_slv_monthly("RDDS43 Query RTT", "rsm.slv.rdds43.rtt", $hostid, $tld, '{$RSM.SLV.RDDS.RTT}');
			__create_slv_monthly("RDDS43 Query RTT", "rsm.slv.rdds80.rtt", $hostid, $tld, '{$RSM.SLV.RDDS.RTT}');
		}

		if ($epp_enabled == 1)
		{
			__create_slv_monthly("DNS update time", "rsm.slv.dns.udp.upd", $hostid, $tld, '{$RSM.SLV.DNS.NS.UPD}');

			if ($rdds_enabled == 1)
			{
				__create_slv_monthly("RDDS update time", "rsm.slv.rdds43.upd", $hostid, $tld, '{$RSM.SLV.RDDS.UPD}');
			}

			__create_slv_monthly('EPP Session-Command RTT',   'rsm.slv.epp.rtt.login',  $hostid, $tld, '{$RSM.SLV.EPP.LOGIN}');
			__create_slv_monthly('EPP Query-Command RTT',     'rsm.slv.epp.rtt.info',   $hostid, $tld, '{$RSM.SLV.EPP.INFO}');
			__create_slv_monthly('EPP Transform-Command RTT', 'rsm.slv.epp.rtt.update', $hostid, $tld, '{$RSM.SLV.EPP.UPDATE}');
		}
	}

	undef($tld);
}
