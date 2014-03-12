#!/usr/bin/perl
#

use lib '/opt/zabbix/scripts';

use strict;
use Zabbix;
use DBI;
use DNSTest;
use Data::Dumper;

my $command = shift || 'total';

my $type = shift || 'dns';

my $hosts;

my $config = get_dnstest_config();


my $dbh = DBI->connect('DBI:mysql:'.$config->{'db'}->{'name'}.':'.$config->{'db'}->{'host'},
                                           $config->{'db'}->{'user'},
                                           $config->{'db'}->{'password'});

my $sql = "select IFNULL(value, 60) FROM globalmacro gm WHERE macro = ?";

my $sth = $dbh->prepare($sql) or die $dbh->errstr;

$sth->execute('{$DNSTEST.PROBE.AVAIL.LIMIT}') or die $dbh->errstr;

my @macro = $sth->fetchrow_array;

$sth->finish;

my $max_diff = $macro[0];

my $sql = "select hostid, host FROM hosts h JOIN hosts_groups hg USING(hostid) JOIN groups g USING(groupid) WHERE g.name = ?";

my $sth = $dbh->prepare($sql) or die $dbh->errstr;

$sth->execute('Probes') or die $dbh->errstr;

while (my $row = $sth->fetchrow_hashref) {
    my $hostid = $row->{'hostid'};
    my @templates;
    push(@templates, $row->{'hostid'});

    $hosts->{$hostid}->{'name'} = $row->{'host'};

    @templates = (@templates, get_parent_templateids($row->{'hostid'}));

    foreach my $templateid (@templates) {
	my $sql = "SELECT hostid, macro, value FROM hostmacro WHERE hostid = ?";

	my $sth = $dbh->prepare($sql) or die $dbh->errstr;               
                                                                 
	$sth->execute($templateid) or die $dbh->errstr;

	while (my $macro = $sth->fetchrow_hashref) {
	    $hosts->{$hostid}->{$macro->{'macro'}} = $macro->{'value'};
	}

	$sth->finish;
    }

    my $sql = "SELECT IFNULL(min(lastvalue),1) as value FROM items where hostid = ? AND key_ LIKE 'dnstest.probe.status[%]'";

    my $sth = $dbh->prepare($sql) or die $dbh->errstr;

    $sth->execute($hostid) or die $dbh->errstr;

    while (my $item = $sth->fetchrow_hashref) {
	$hosts->{$hostid}->{'status'} = $item->{'value'};
    }

    $sth->finish;

    my $sql = "SELECT lastclock, lastvalue FROM items i JOIN hosts h USING (hostid) where h.host = ? AND key_ = 'zabbix[proxy,{\$DNSTEST.PROXY_NAME},lastaccess]'";

    my $sth = $dbh->prepare($sql) or die $dbh->errstr;

    $sth->execute($row->{'host'}.' - mon') or die $dbh->errstr;

    while (my $item = $sth->fetchrow_hashref) {
	$hosts->{$hostid}->{'status'} = 0 if (time - $item->{'lastvalue'} > $max_diff);
    }

    $sth->finish;
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

    my $sql = "SELECT templateid FROM hosts_templates WHERE hostid = ?";

    my $sth = $dbh->prepare($sql) or die $dbh->errstr;

    $sth->execute($templateid) or die $dbh->errstr;

    while (my $row = $sth->fetchrow_hashref) {
	my $templateid = $row->{'templateid'};
	push (@result, $templateid);
        my @parent_templates = get_parent_templateids($templateid);
        @result = (@result, @parent_templates) if scalar @parent_templates;
    }

    $sth->finish;

    return @result;
}
