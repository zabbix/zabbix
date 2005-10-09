#!/bin/sh

aclocal
autoconf
autoheader
automake -a
automake
# Change ./configure options if needed
#./configure --with-mysql --prefix=/home/zabbix/zabbix --with-net-snmp

rm -f config.guess config.sub depcomp install-sh missing

cp /usr/share/automake-1.9/config.guess	config.guess
cp /usr/share/automake-1.9/config.sub	config.sub
cp /usr/share/automake-1.9/depcomp	depcomp
cp /usr/share/automake-1.9/install-sh	install-sh
cp /usr/share/automake-1.9/missing	missing

sleep 2
echo Press any key to continue
#read

./configure --enable-agent --enable-server --with-mysql --prefix=/home/zabbix/zabbix
make install
