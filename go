#!/bin/sh

aclocal
autoconf
autoheader
automake -a
automake
# Change ./configure options if needed
./configure --enable-server --with-mysql --prefix=/home/zabbix/zabbix --with-net-snmp
#./configure --prefix=/home/zabbix/zabbix
make
make install
