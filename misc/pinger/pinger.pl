#!/usr/bin/perl

# CONFIGURATION

$ZABBIX_SERVER="zabbix";
$ZABBIX_PORT="10001";
$HOST_FILE="hosts";
$TMP_FILE="/tmp/zabbix.pinger.tmp";

# END OF CONFIGURATION

$hosts = `cat $HOST_FILE | fping`;

system("rm -f $TMP_FILE");

foreach $host (split(/\n/,$hosts))
{
	if($host=~/^((.)*) is alive$/)
	{
		$cmd="echo $ZABBIX_SERVER $ZABBIX_PORT $1:alive 1 >>$TMP_FILE"; 
	}
	else
	{
		$host=~/^((.)*) is((.)*)$/;
		$cmd="echo $ZABBIX_SERVER $ZABBIX_PORT $1:alive 0 >>$TMP_FILE"; 
	}
#	print $cmd,"\n";
	system( $cmd );	
}

$cmd="zabbix_sender <$TMP_FILE";

system($cmd);
