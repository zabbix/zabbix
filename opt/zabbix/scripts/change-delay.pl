#!/usr/bin/perl -w

use lib '/opt/zabbix/scripts';

use RSM;
use RSMSLV;
use Getopt::Long;

set_slv_config(get_rsm_config());

my %OPTS;
__usage() unless (GetOptions(\%OPTS, "type=n", "delay=n", "help!"));
__usage() if ($OPTS{'help'});
__usage() unless (__validate_input() == SUCCESS);

my ($key_part, $macro, $sql);
if ($OPTS{'type'} == 1)
{
    $key_part = 'rsm.dns.udp[%';
    $macro = '{$RSM.DNS.UDP.DELAY}';
}
elsif ($OPTS{'type'} == 2)
{
    $key_part = 'rsm.dns.tcp[%';
    $macro = '{$RSM.DNS.TCP.DELAY}';
}
elsif ($OPTS{'type'} == 3)
{
    $key_part = 'rsm.rdds[%';
    $macro = '{$RSM.RDDS.DELAY}';
}
elsif ($OPTS{'type'} == 4)
{
    $key_part = 'rsm.epp[%';
    $macro = '{$RSM.EPP.DELAY}';
}

db_connect();

$sql = "update items set delay=? where type=3 and key_ like ?";
$sth = $dbh->prepare($sql) or die $dbh->errstr;
$sth->execute($OPTS{'delay'}, $key_part) or die $dbh->errstr;

$sql = "update globalmacro set value=? where macro=?";
$sth = $dbh->prepare($sql) or die $dbh->errstr;
$sth->execute($OPTS{'delay'}, $macro) or die $dbh->errstr;

sub __validate_input
{
    return FAIL unless ($OPTS{'type'} and $OPTS{'delay'});
    return FAIL unless ($OPTS{'type'} >= 1 and $OPTS{'type'} <= 4);
    return FAIL unless ($OPTS{'delay'} >= 60 and $OPTS{'delay'} <= 3600);

    return SUCCESS;
}

sub __usage
{
    print("usage: $0 <options>\n");
    print("Options:\n");
    print("    --type <n>      test type: 1 - DNS UDP, 2 - DNS TCP, 3 - RDDS, 4 - EPP\n");
    print("    --delay <n>     test delay in seconds\n");
    print("    --help          print this message\n");
    exit(FAIL);
}
