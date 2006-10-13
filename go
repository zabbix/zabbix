#!/bin/sh

clear

rm -f WARNINGS

echo Pre-making...
aclocal
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
export CFLAGS="-Wall"
#export CFLAGS="-Wall -pedantic"
./configure --enable-agent --enable-server --with-mysql --with-net-snmp --prefix=`pwd` 2>>WARNINGS >/dev/null
#./configure --enable-agent --enable-server --with-pgsql --with-net-snmp --prefix=`pwd` 2>>WARNINGS >/dev/null
#./configure --enable-agent --enable-server --with-oracle=/home/zabbix/sqlora8 --with-net-snmp --prefix=`pwd` 2>>WARNINGS >/dev/null
echo Cleaning...
make clean 2>>WARNINGS >/dev/null
echo Making...
make 2>>WARNINGS >/dev/null
#echo Installing...
make install 2>>WARNINGS >/dev/null

echo
echo WARNINGS
echo "-----------------------------------"
cat WARNINGS
echo "-----------------------------------"
