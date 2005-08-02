#!/bin/sh

aclocal
autoconf
autoheader
automake -a
automake
# Change ./configure options if needed
./configure --enable-server --with-mysql --prefix=/home/zabbix/zabbix
#./configure --prefix=/home/zabbix/zabbix
make
make install
