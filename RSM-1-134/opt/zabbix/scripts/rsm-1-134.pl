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
my $dns_test_valuemapid = 13;
my $dns_test_result_valuemapid = 20;
my $epp_test_result_valuemapid = 21;

info("fixing items...");
db_exec("update items set name='DNS test',key_='rsm.dns[{\$RSM.TLD}]' where key_='rsm.dns.udp[{\$RSM.TLD}]'");
db_exec("update items set name='RDDS43 update time' where key_='rsm.slv.rdds43.upd'");
db_exec("update items set name='DNS UDP update time' where key_='rsm.slv.dns.udp.upd'");
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
my $global_macros =
{
	'{$RSM.DNS.TEST.PROTO.RATIO}' => 10,
        '{$RSM.DNS.TEST.UPD.RATIO}' => 2,
        '{$RSM.DNS.TEST.CRIT.RECOVER}' => 3
};
__bulk_macro_create($global_macros, undef);	# do not force update

my $tlds_ref = get_tlds(ENABLED_EPP);
my @items_to_create;
my @items_to_update;
my @tmpl_hostids;
my $applications;
foreach (@{$tlds_ref})
{
	$tld = $_;

	my $hostid = get_hostid($tld);
	my $tmpl_hostid = get_hostid("Template $tld");

	my ($rdds43, $rdds80, $rdap) = get_macro_rdds_enabled($tmpl_hostid);

	__create_slv_monthly('DNS update time', 'rsm.slv.dns.upd', $hostid, $tld, '{$RSM.SLV.DNS.NS.UPD}');
	__create_slv_monthly('DNS TCP update time', 'rsm.slv.dns.tcp.upd', $hostid, 0, 0);	# no trigger

	push(@tmpl_hostids, $tmpl_hostid);
}
undef($tld);

my $items_ref = get_items_by_hostids(\@tmpl_hostids, 'rsm.dns.udp.upd[', 0, 1);	# incomplete key, with keys

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

my $mappingid = 273;

$rows_ref = db_select("select 1 from mappings where newvalue='Down (UDP:down, TCP:down)'");
if (scalar(@{$rows_ref}) == 0)
{
	info("fixing service value map names...");
	db_exec("update valuemaps set name='DNS Test' where valuemapid=13");
	db_exec("update valuemaps set name='RDDS Test' where valuemapid=15");
	db_exec("update valuemaps set name='EPP Test' where valuemapid=19");
	db_exec("update valuemaps set name='RDDS Test result' where valuemapid=18");
	db_exec("update valuemaps set name='Service availability' where valuemapid=16");
	db_exec("update valuemaps set name='Probe status' where valuemapid=14");
	db_exec("insert into valuemaps (valuemapid,name) values ($dns_test_result_valuemapid,'DNS Test result')");

	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'2','Up (UDP:up)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'3','Up (TCP:up)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'4','Up (UDP:up, TCP:up)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'5','Down (UDP:down)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'6','Down (TCP:down)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'7','Down (UDP:up, TCP:down)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'8','Down (UDP:down, TCP:up)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'9','Down (UDP:down, TCP:down)')");

	db_exec("insert into valuemaps (valuemapid,name) values ($epp_test_result_valuemapid,'EPP Test result')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$epp_test_result_valuemapid,'0','Down')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$epp_test_result_valuemapid,'1','Up')");
}

db_exec("update mappings set newvalue='No reply from resolver (obsolete)' where valuemapid=$dns_test_valuemapid and mappingid=75");
db_exec("update mappings set newvalue='Keyset is not valid (obsolete)' where valuemapid=$dns_test_valuemapid and mappingid=76");
db_exec("update mappings set newvalue='DNS UDP - No reply from name server' where valuemapid=$dns_test_valuemapid and mappingid=78");
db_exec("update mappings set newvalue='Invalid reply from Name Server (obsolete)' where valuemapid=$dns_test_valuemapid and mappingid=79");
db_exec("update mappings set newvalue='No UNIX timestamp (obsolete)' where valuemapid=$dns_test_valuemapid and mappingid=80");
db_exec("update mappings set newvalue='Invalid UNIX timestamp (obsolete)' where valuemapid=$dns_test_valuemapid and mappingid=81");
db_exec("update mappings set newvalue='DNSSEC error (obsolete)' where valuemapid=$dns_test_valuemapid and mappingid=82");

$mappingid = 283;

$rows_ref = db_select("select 1 from mappings where mappingid=284");
if (scalar(@{$rows_ref}) == 0)
{
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-207','DNS UDP - Expecting DNS class IN but got CHAOS')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-208','DNS UDP - Expecting DNS Class IN but got HESIOD')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-209','DNS UDP - Expecting DNS Class IN but got something different than IN, CHAOS or HESIOD')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-210','DNS UDP - Header section incomplete')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-211','DNS UDP - Question section incomplete')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-212','DNS UDP - Answer section incomplete')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-213','DNS UDP - Authority section incomplete')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-214','DNS UDP - Additional section incomplete')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-215','DNS UDP - Malformed DNS response')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-250','DNS UDP - Querying for a non existent domain - AA flag not present in response')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-251','DNS UDP - Querying for a non existent domain - Domain name being queried not present in question section')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-252','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOERROR')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-253','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got FORMERR')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-254','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got SERVFAIL')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-255','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTIMP')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-256','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got REFUSED')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-257','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got YXDOMAIN')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-258','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got YXRRSET')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-259','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NXRRSET')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-260','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTAUTH')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-261','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTZONE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-262','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADVERS or BADSIG')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-263','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADKEY')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-264','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADTIME')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-265','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADMODE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-266','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADNAME')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-267','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADALG')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-268','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADTRUNC')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-269','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADCOOKIE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-270','DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got unexpected')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-300','DNS UDP - Querying for an existent domain - AA flag present in response')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-301','DNS UDP - Querying for an existent domain - Domain name being queried not present in question section')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-302','DNS UDP - Querying for an existent domain - Expecting referral but answer section is not empty')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-303','DNS UDP - Querying for an existent domain - Expecting referral but authority section does not contain a referral')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-304','DNS UDP - Querying for an existent domain - No Unix timestamp')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-305','DNS UDP - Querying for an existent domain - Invalid timestamp')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-306','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got FORMERR')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-307','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got SERVFAIL')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-308','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got NXDOMAIN')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-309','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got NOTIMP')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-310','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got REFUSED')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-311','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got YXDOMAIN')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-312','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got YXRRSET')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-313','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got NXRRSET')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-314','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got NOTAUTH')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-315','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got NOTZONE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-316','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got BADVERS or BADSIG')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-317','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got BADKEY')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-318','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got BADTIME')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-319','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got BADMODE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-320','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got BADNAME')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-321','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got BADALG')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-322','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got BADTRUNC')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-323','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got BADCOOKIE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-324','DNS UDP - Querying for an existent domain - Expecting NOERROR RCODE but got unexpected')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-350','DNS UDP - Querying for a DNAME TLD - AA flag present in response')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-351','DNS UDP - Querying for a DNAME TLD - DNAME RR not found or malformed in answer section')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-352','DNS UDP - Querying for a DNAME TLD - CNAME RR not found in answer section')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-353','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got FORMERR')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-354','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got SERVFAIL')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-355','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got NXDOMAIN')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-356','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got NOTIMP')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-357','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got REFUSED')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-358','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got YXDOMAIN')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-359','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got YXRRSET')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-360','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got NXRRSET')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-361','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got NOTAUTH')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-362','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got NOTZONE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-363','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got BADVERS or BADSIG')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-364','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got BADKEY')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-365','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got BADTIME')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-366','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got BADMODE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-367','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got BADNAME')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-368','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got BADALG')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-369','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got BADTRUNC')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-370','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got BADCOOKIE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-371','DNS UDP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got unexpected')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-400','DNS UDP - No reply from local resolver')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-401','DNS UDP - No AD bit from local resolver')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-402','DNS UDP - Expecting NOERROR RCODE but got SERVFAIL from local resolver')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-403','DNS UDP - Expecting NOERROR RCODE but got NXDOMAIN from local resolver')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-404','DNS UDP - Expecting NOERROR RCODE but got unexpecting from local resolver')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-405','DNS UDP - Unknown cryptographic algorithm')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-406','DNS UDP - Cryptographic algorithm not implemented')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-407','DNS UDP - No RRSIGs where found in any section, and the TLD has the DNSSEC flag enabled')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-408','DNS UDP - LDNS - No RRSIG found')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-409','DNS UDP - No DNSSEC public key(s)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-410','DNS UDP - The signature does not cover this RRset')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-411','DNS UDP - No signatures found for trusted DNSSEC public key(s)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-412','DNS UDP - No DS record(s)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-413','DNS UDP - Could not validate DS record(s)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-414','DNS UDP - No keys with the KEYTAG and algorithm from the RRSIG found')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-415','DNS UDP - Bogus DNSSEC signature')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-416','DNS UDP - DNSSEC signature has expired')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-417','DNS UDP - DNSSEC signature not incepted yet')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-418','DNS UDP - DNSSEC signature has expiration date earlier than inception date')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-419','DNS UDP - Error in NSEC3 denial of existence proof')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-420','DNS UDP - Syntax error, algorithm unknown or non parseable')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-421','DNS UDP - Iterations count for NSEC3 record higher than maximum')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-422','DNS UDP - RR not covered by the given NSEC RRs')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-423','DNS UDP - Wildcard not covered by the given NSEC RRs')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-424','DNS UDP - Original of NSEC3 hashed name could not be found')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-425','DNS UDP - The RRSIG has too few RDATA fields')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-426','DNS UDP - The DNSKEY has too few RDATA fields')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-427','DNS UDP - Malformed DNSSEC response')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-600','DNS TCP - Timeout reply from name server')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-601','DNS TCP - Error opening connection to name server')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-607','DNS TCP - Expecting DNS class IN but got CHAOS')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-608','DNS TCP - Expecting DNS Class IN but got HESIOD')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-609','DNS TCP - Expecting DNS Class IN but got something different than IN, CHAOS or HESIOD')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-610','DNS TCP - Header section incomplete')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-611','DNS TCP - Question section incomplete')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-612','DNS TCP - Answer section incomplete')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-613','DNS TCP - Authority section incomplete')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-614','DNS TCP - Additional section incomplete')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-615','DNS TCP - Malformed DNS response')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-650','DNS TCP - Querying for a non existent domain - AA flag not present in response')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-651','DNS TCP - Querying for a non existent domain - Domain name being queried not present in question section')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-652','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOERROR')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-653','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got FORMERR')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-654','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got SERVFAIL')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-655','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTIMP')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-656','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got REFUSED')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-657','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got YXDOMAIN')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-658','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got YXRRSET')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-659','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NXRRSET')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-660','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTAUTH')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-661','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTZONE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-662','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADVERS or BADSIG')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-663','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADKEY')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-664','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADTIME')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-665','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADMODE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-666','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADNAME')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-667','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADALG')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-668','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADTRUNC')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-669','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADCOOKIE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-670','DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got unexpected')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-700','DNS TCP - Querying for an existent domain - AA flag present in response')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-701','DNS TCP - Querying for an existent domain - Domain name being queried not present in question section')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-702','DNS TCP - Querying for an existent domain - Expecting referral but answer section is not empty')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-703','DNS TCP - Querying for an existent domain - Expecting referral but authority section does not contain a referral')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-704','DNS TCP - Querying for an existent domain - No Unix timestamp')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-705','DNS TCP - Querying for an existent domain - Invalid timestamp')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-706','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got FORMERR')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-707','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got SERVFAIL')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-708','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got NXDOMAIN')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-709','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got NOTIMP')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-710','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got REFUSED')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-711','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got YXDOMAIN')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-712','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got YXRRSET')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-713','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got NXRRSET')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-714','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got NOTAUTH')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-715','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got NOTZONE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-716','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got BADVERS or BADSIG')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-717','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got BADKEY')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-718','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got BADTIME')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-719','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got BADMODE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-720','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got BADNAME')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-721','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got BADALG')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-722','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got BADTRUNC')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-723','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got BADCOOKIE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-724','DNS TCP - Querying for an existent domain - Expecting NOERROR RCODE but got unexpected')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-750','DNS TCP - Querying for a DNAME TLD - AA flag present in response')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-751','DNS TCP - Querying for a DNAME TLD - DNAME RR not found or malformed in answer section')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-752','DNS TCP - Querying for a DNAME TLD - CNAME RR not found in answer section')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-753','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got FORMERR')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-754','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got SERVFAIL')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-755','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got NXDOMAIN')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-756','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got NOTIMP')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-757','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got REFUSED')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-758','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got YXDOMAIN')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-759','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got YXRRSET')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-760','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got NXRRSET')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-761','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got NOTAUTH')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-762','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got NOTZONE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-763','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got BADVERS or BADSIG')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-764','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got BADKEY')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-765','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got BADTIME')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-766','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got BADMODE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-767','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got BADNAME')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-768','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got BADALG')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-769','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got BADTRUNC')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-770','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got BADCOOKIE')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-771','DNS TCP - Querying for a DNAME TLD - Expecting NOERROR RCODE but got unexpected')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-800','DNS TCP - No reply from local resolver')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-801','DNS TCP - No AD bit from local resolver')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-802','DNS TCP - Expecting NOERROR RCODE but got SERVFAIL from local resolver')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-803','DNS TCP - Expecting NOERROR RCODE but got NXDOMAIN from local resolver')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-804','DNS TCP - Expecting NOERROR RCODE but got unexpecting from local resolver')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-805','DNS TCP - Unknown cryptographic algorithm')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-806','DNS TCP - Cryptographic algorithm not implemented')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-807','DNS TCP - No RRSIGs where found in any section, and the TLD has the DNSSEC flag enabled')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-808','DNS TCP - LDNS - No RRSIG found')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-809','DNS TCP - No DNSSEC public key(s)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-810','DNS TCP - The signature does not cover this RRset')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-811','DNS TCP - No signatures found for trusted DNSSEC public key(s)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-812','DNS TCP - No DS record(s)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-813','DNS TCP - Could not validate DS record(s)')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-814','DNS TCP - No keys with the KEYTAG and algorithm from the RRSIG found')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-815','DNS TCP - Bogus DNSSEC signature')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-816','DNS TCP - DNSSEC signature has expired')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-817','DNS TCP - DNSSEC signature not incepted yet')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-818','DNS TCP - DNSSEC signature has expiration date earlier than inception date')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-819','DNS TCP - Error in NSEC3 denial of existence proof')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-820','DNS TCP - Syntax error, algorithm unknown or non parseable')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-821','DNS TCP - Iterations count for NSEC3 record higher than maximum')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-822','DNS TCP - RR not covered by the given NSEC RRs')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-823','DNS TCP - Wildcard not covered by the given NSEC RRs')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-824','DNS TCP - Original of NSEC3 hashed name could not be found')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-825','DNS TCP - The RRSIG has too few RDATA fields')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-826','DNS TCP - The DNSKEY has too few RDATA fields')");
	db_exec("insert into mappings (mappingid,valuemapid,value,newvalue) values (".++$mappingid.",$dns_test_result_valuemapid,'-827','DNS TCP - Malformed DNSSEC response')");
}

$rows_ref = db_select("select itemid from items where key_='rsm.dns[{\$RSM.TLD}]' and templateid is null");
my @dns_items_to_update;
foreach my $row_ref (@{$rows_ref})
{
	my $options =
	{
		'itemid' => $row_ref->[0],
		'valuemapid' => $dns_test_result_valuemapid
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
		'valuemapid' => $epp_test_result_valuemapid
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

sub __create_slv_monthly
{
	my $test_name = shift;
	my $key_base = shift;
	my $hostid = shift;
	my $host_name = shift;
	my $macro = shift;

	unless ($zabbix->exist('item', {'hostid' => $hostid, 'key_' => $key_base . '.pfailed'}))
	{
		my $applicationid = __get_application_id($hostid, APP_SLV_MONTHLY);

		my $pfailed_item = __create_slv_item($test_name.': % of failed tests', $key_base.'.pfailed', $hostid, VALUE_TYPE_PERC, [$applicationid]);
		__create_slv_item($test_name.': # of failed tests', $key_base.'.failed', $hostid, VALUE_TYPE_NUM, [$applicationid]);
		__create_slv_item($test_name.': expected # of tests', $key_base.'.max', $hostid, VALUE_TYPE_NUM, [$applicationid]);
		my $avg_item = __create_slv_item($test_name.': average cycle result', $key_base.'.avg', $hostid, VALUE_TYPE_DOUBLE, [$applicationid]);

		__create_graph($test_name.' - Average', [{'itemid' => $avg_item, 'hostid' => $hostid}]);

		if ($host_name)
		{
			__create_graph($test_name.' - Ratio of Failed tests', [{'itemid' => $pfailed_item, 'hostid' => $hostid}]);

			my $options =
			{
				'description' => $test_name . ' < ' . $macro . '%',
				'expression' => '{'.$host_name.':'.$key_base.'.pfailed.last(0)}<'.$macro,
				'priority' => '4'
			};

			__create_trigger($options);
		}
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

sub __create_graph
{
	my $name = shift;
	my $items = shift;

	my $items_count = scalar(@{$items});

	return if ($items_count == 0);

	my @hostids = ();
	my @gitems = ();

	my @color_map = ('1A7C11', 'F63100', '2774A4', 'A54F10', 'FC6EA3', '6C59DC', 'AC8C14', '611F27', 'F230E0',
			'5CCD18', 'BB2A02', '5A2B57', '89ABF8', '7EC25C', '274482', '2B5429', '8048B4', 'FD5434',
			'790E1F', '87AC4D', 'E89DF4');
	my @color_tmp = @color_map;

	foreach (@{$items})
	{
		my $options = $_;

		if (scalar(@color_tmp) == 0)
		{
			@color_tmp = @color_map;
		}

		my $color = shift(@color_tmp);

		$options->{'color'} = $color;

		push(@gitems, $options);
		push(@hostids, $_->{'hostid'});
	}

	my $result = $zabbix->get('graph', {'hostids' => [@hostids], 'filter' => {'name' => $name}});

	if (exists($result->{'graphid'}))
	{
		$result = $zabbix->update('graph', {'graphid' => $result->{'graphid'}, 'name' => $name,
				'gitems' => [@gitems]});
	}
	else
	{
		$result = $zabbix->create('graph', {'name' => $name, 'gitems' => [@gitems], 'width' => 900,
				'height' => 200});
	}

	return $result;
}

sub __bulk_macro_create
{
	my $macros = shift;
	my $force_update = shift;

	my $macro_to_update = {};
	my @data;

	my $zbx_macros = $zabbix->get('usermacro',{'output' => 'extend', 'globalmacro' => 1, 'preservekeys' => 1} );

	foreach my $globalmacroid (keys %{$zbx_macros})
	{
		my $value = $zbx_macros->{$globalmacroid}->{'value'};
		my $macro = $zbx_macros->{$globalmacroid}->{'macro'};

		next unless exists $macros->{$macro};

		if ($value eq $macros->{$macro})
		{
			delete $macros->{$macro};
		}
		else
		{
			$macro_to_update->{$macro} = $globalmacroid;
		}
	}

	foreach my $macro (keys %{$macros})
	{
		my $value = $macros->{$macro};
		if (exists($macro_to_update->{$macro}))
		{
			my $globalmacroid = $macro_to_update->{$macro};
			$zabbix->macro_global_update({'globalmacroid' => $globalmacroid, 'value' => $value}) if defined($force_update);
		}
		else
		{
			push @data, {'macro' => $macro, 'value' => $value};
		}
	}

	if (scalar(@data))
	{
		$zabbix->macro_global_create(\@data);
	}
}
