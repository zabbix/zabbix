#!/bin/sh

aclocal
autoconf
autoheader
automake -a
automake
# Change ./configure options if needed
#./configure --with-mysql --prefix=/home/zabbix/zabbix --with-net-snmp
#./configure --enable-agent --enable-server --with-mysql --prefix=/home/zabbix/zabbix
#make install
