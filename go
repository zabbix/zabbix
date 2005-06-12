#!/bin/sh

aclocal
autoconf
autoheader
automake -a
automake
./configure --with-mysql --prefix=/home/zabbix/zabbix
make
make install
