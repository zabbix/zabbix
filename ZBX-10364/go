#!/bin/bash

clear
rm -f WARNINGS

echo Pre-making...
aclocal -I m4
autoconf
autoheader
automake -a
automake
# Change ./configure options if needed
#./configure --with-mysql --prefix=/home/zabbix/zabbix --with-net-snmp

#rm -f config.guess config.sub depcomp install-sh missing

#cp /usr/share/automake-1.9/config.guess	config.guess
#cp /usr/share/automake-1.9/config.sub	config.sub
#cp /usr/share/automake-1.9/depcomp	depcomp
#cp /usr/share/automake-1.9/install-sh	install-sh
#cp /usr/share/automake-1.9/missing	missing

#cd ~zabbix
#rm -f zabbix.tgz
#tar cvzf zabbix.tgz zabbix
#exit
echo Configuring...
#export CFLAGS="-Wall -Wuninitialized -O -DDEBUG"
export CFLAGS="-Wall -Wuninitialized -O -g"
#export CFLAGS="-Wall -pedantic"
#for db in sqlite3 pgsql mysql; do
for db in pgsql; do
	./configure --enable-proxy --enable-agent --enable-server --with-jabber --with-ldap --with-libcurl --with-$db --with-net-snmp --prefix=`pwd` 2>>WARNINGS >/dev/null
# ORACLE parameters --with-oracle --with-oracle-include=/usr/include/oracle/11.1/client64/ --with-oracle-lib=/usr/lib/oracle/11.1/client64/lib/>/dev/null
	echo Cleaning...
	make -j4 clean 2>>WARNINGS >/dev/null
	echo Making dbschema...
	make -j4 dbschema 2>>WARNINGS >/dev/null
	echo Making...
	make 2>>WARNINGS >/dev/null
	echo Installing...
	make -j4 install 2>>WARNINGS >/dev/null
done

echo
echo WARNINGS
echo "-----------------------------------"
cat WARNINGS
echo "-----------------------------------"
