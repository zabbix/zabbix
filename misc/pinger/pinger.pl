#!/usr/bin/perl

# 
# Zabbix
# Copyright (C) 2000,2001,2002,2003 Alexei Vladishev
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
#

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
	system( $cmd );	
}

$cmd="zabbix_sender <$TMP_FILE";

system($cmd);
