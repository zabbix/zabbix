#!/usr/bin/perl

$dead_hosts = `cat /home/monitor/hosts | fping -u`;

foreach $host (split(/\n/,$dead_hosts))
{
	$cmd="/home/monitor/monitor/src/mon_sender/mon_sender arsenal 10001 $host:alive 0"; 
	print $cmd,"\n";
	system( $cmd );	
}

$alive_hosts = `cat /home/monitor/hosts | fping -a`;

foreach $host (split(/\n/,$alive_hosts))
{
	$cmd="/home/monitor/monitor/src/mon_sender/mon_sender arsenal 10001 $host:alive 1"; 
	print $cmd,"\n";
	system( $cmd );	
}


