#!/bin/sh

aclocal
autoconf
autoheader
automake -a
automake
# Change ./configure options if needed
./configure --with-mysql --prefix=/home/alexei/zabbix
make
make install
