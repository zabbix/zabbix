#!/usr/bin/perl

$hosts = `cat /home/zabbix/pinger/hosts | fping`;

foreach $host (split(/\n/,$hosts))
{
	if($host=~/^((.)*) is alive$/)
	{
		$cmd="/home/zabbix/bin/zabbix_sender arsenal 10001 $1:alive 1"; 
	}
	else
	{
		$host=~/^((.)*) is((.)*)$/;
		$cmd="/home/zabbix/bin/zabbix_sender arsenal 10001 $1:alive 0"; 
	}
	print $cmd,"\n";
	system( $cmd );	
}
