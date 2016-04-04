#!/bin/bash
#
# Zabbix
# Copyright (C) 2001-2013 Zabbix SIA
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#

# CONFIGURATION

ZABBIX_SERVER="localhost";
ZABBIX_PORT="10051";

ZABBIX_SENDER="~zabbix/bin/zabbix_sender";

KEY="snmptraps";
HOST="snmptraps";

# END OF CONFIGURATION

read hostname
read ip
read uptime
read oid
read address
read community
read enterprise

oid=`echo $oid|cut -f2 -d' '`
address=`echo $address|cut -f2 -d' '`
community=`echo $community|cut -f2 -d' '`
enterprise=`echo $enterprise|cut -f2 -d' '`

oid=`echo $oid|cut -f11 -d'.'`
community=`echo $community|cut -f2 -d'"'`

str="$hostname $address $community $enterprise $oid"

$ZABBIX_SENDER -z $ZABBIX_SERVER -p $ZABBIX_PORT -s $HOST -k $KEY -o "$str"
