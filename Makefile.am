## Process this file with automake to produce Makefile.in

ACLOCAL_AMFLAGS = -I m4

SUBDIRS = \
	src \
	create \
	man \
	misc \
	upgrades

EXTRA_DIST = \
	bin \
	build \
	frontends \
	include \
	CREDITS

## "dist-hook" run after the distribution directory is filled, but before the actual tar (or shar) file is created.
dist-hook:
	@zabbix_revision=`svn info|grep "Last Changed Rev"|awk '{print $$4;}'`; \
	cat $(top_distdir)/include/common.h|sed "s/{ZABBIX_REVISION}/$$zabbix_revision/g" >$(top_distdir)/include/common.h.new
	mv $(top_distdir)/include/common.h.new $(top_distdir)/include/common.h

	rm -rf `find $(top_distdir) -name ".svn"`
	rm -f $(top_distdir)/include/config.h
	rm -f $(top_distdir)/frontends/php/conf/zabbix.conf.php
	rm -rf $(top_distdir)/frontends/php/tests

dbschema:
	create/schema/gen.pl ibm_db2 > create/schema/ibm_db2.sql
	create/schema/gen.pl mysql > create/schema/mysql.sql
	create/schema/gen.pl oracle > create/schema/oracle.sql
	create/schema/gen.pl postgresql > create/schema/postgresql.sql
	create/schema/gen.pl sqlite3 > create/schema/sqlite3.sql
	create/schema/gen.pl c > src/libs/zbxdbhigh/dbschema.c

gettext:
	frontends/php/locale/make_mo.sh
