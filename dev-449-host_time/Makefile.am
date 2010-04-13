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
	docs \
	FAQ \
	frontends \
	include \
	CREDITS

## "dist-hook" run after the distribution directory is filled, but before the actual tar (or shar) file is created.
dist-hook:
# Update revision in common.h
	@zabbix_revision=`svn info|grep "Last Changed Rev"|awk '{print $$4;}'`; \
	cat $(top_distdir)/include/common.h|sed "s/{ZABBIX_REVISION}/$$zabbix_revision/g" >$(top_distdir)/include/common.h.new
	mv $(top_distdir)/include/common.h.new $(top_distdir)/include/common.h

	rm -rf `find $(top_distdir) -name ".svn"`
	rm -f $(top_distdir)/frontends/php/conf/zabbix.conf.php
	rm -f $(top_distdir)/do
	rm -f $(top_distdir)/go
	rm -f $(top_distdir)/splint
	rm -rf $(top_distdir)/test
	rm -f $(top_distdir)/TODO
##TODO  mkdir $(top_distdir)/docs
##TODO  cp ?????/ZABBIX Manual v1.4.pdf $(top_distdir)/docs/

dbschema:
	create/schema/gen.pl mysql > create/schema/mysql.sql
	create/schema/gen.pl postgresql > create/schema/postgresql.sql
	create/schema/gen.pl sqlite > create/schema/sqlite.sql
	create/schema/gen.pl oracle > create/schema/oracle.sql
	create/schema/gen.pl c >src/libs/zbxdbhigh/dbschema.c
	echo -e "\n#ifdef HAVE_MYSQL\nconst char *db_schema= {\"\\" >>src/libs/zbxdbhigh/dbschema.c
	create/schema/gen.pl mysql|tr -s '\t' ' '|sed -e 's/$$/\\/' >>src/libs/zbxdbhigh/dbschema.c
	echo -e "\"};\n#elif HAVE_POSTGRESQL\nconst char *db_schema = {\"\\" >>src/libs/zbxdbhigh/dbschema.c
	create/schema/gen.pl postgresql|tr -s '\t' ' '|sed -e 's/$$/\\/' >>src/libs/zbxdbhigh/dbschema.c
	echo -e "\"};\n#elif HAVE_ORACLE\nconst char *db_schema = {\"\\" >>src/libs/zbxdbhigh/dbschema.c
	create/schema/gen.pl oracle|tr -s '\t' ' '|sed -e 's/$$/\\/' >>src/libs/zbxdbhigh/dbschema.c
	echo -e "\"};\n#elif HAVE_SQLITE3\nconst char *db_schema = {\"\\"  >>src/libs/zbxdbhigh/dbschema.c
	create/schema/gen.pl sqlite|tr -s '\t' ' '|sed -e 's/$$/\\/' >>src/libs/zbxdbhigh/dbschema.c
	echo -e "\"};\n#endif /* HAVE_SQLITE3 */\n" >>src/libs/zbxdbhigh/dbschema.c
