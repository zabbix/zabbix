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
cd create/schema
./gen.pl c >../../src/libs/zbxdbhigh/dbschema.c
echo -e "\n#ifdef HAVE_MYSQL\nconst char *db_schema= {\"\\">>../../src/libs/zbxdbhigh/dbschema.c
./gen.pl mysql|sed -e 's/\t\t*/ /g' -e 's/$/\\/' >>../../src/libs/zbxdbhigh/dbschema.c
echo -e "\"};\n#elif HAVE_POSTGRESQL\nconst char *db_schema = {\"\\">>../../src/libs/zbxdbhigh/dbschema.c
./gen.pl postgresql|sed -e 's/\t\t*/ /g' -e 's/$/\\/' >>../../src/libs/zbxdbhigh/dbschema.c
echo -e "\"};\n#elif HAVE_ORACLE\nconst char *db_schema = {\"\\">>../../src/libs/zbxdbhigh/dbschema.c
./gen.pl oracle|sed -e 's/\t\t*/ /g' -e 's/$/\\/' >>../../src/libs/zbxdbhigh/dbschema.c
echo -e "\"};\n#elif HAVE_SQLITE3\nconst char *db_schema = {\"\\" >>../../src/libs/zbxdbhigh/dbschema.c
./gen.pl sqlite|sed -e 's/\t\t*/ /g' -e 's/$/\\/' >>../../src/libs/zbxdbhigh/dbschema.c
echo -e "\"};\n#endif /* HAVE_SQLITE3 */\n" >>../../src/libs/zbxdbhigh/dbschema.c
cd -
#export CFLAGS="-Wall -pedantic"
#for db in sqlite3 pgsql mysql; do
for db in mysql; do
	./configure --enable-proxy --enable-agent --enable-server --with-jabber --with-ldap --with-libcurl --with-$db --with-net-snmp --prefix=`pwd` --enable-ipv6 2>>WARNINGS >/dev/null
	echo Cleaning...
	make clean 2>>WARNINGS >/dev/null
	echo Making...
	make 2>>WARNINGS >/dev/null
	echo Installing...
	make install 2>>WARNINGS >/dev/null
done

echo
echo WARNINGS
echo "-----------------------------------"
cat WARNINGS
echo "-----------------------------------"
