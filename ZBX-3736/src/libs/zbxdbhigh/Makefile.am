## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxdbhigh.a

if WITH_ODBC
ODBC_SRC = odbc.c
else
ODBC_SRC =
endif

libzbxdbhigh_a_SOURCES = \
	host.c \
	${ODBC_SRC} \
	db.c \
	dbschema.c \
	macro.c	\
	proxy.c \
	discovery.c

libzbxdbhigh_a_CFLAGS = -I@top_srcdir@/src/zabbix_server/

dbschema.c: @top_srcdir@/create/schema/schema.sql
	@top_srcdir@/create/schema/gen.pl c > @top_srcdir@/src/libs/zbxdbhigh/dbschema.c
	echo -e "\n#ifdef HAVE_SQLITE3\nconst char *db_schema= {\"\\" >> @top_srcdir@/src/libs/zbxdbhigh/dbschema.c
	@top_srcdir@/create/schema/gen.pl sqlite|tr -s '\t' ' '|sed -e 's/$$/\\/' >> @top_srcdir@/src/libs/zbxdbhigh/dbschema.c
	echo -e "\"};\n#endif /* HAVE_SQLITE3 */\n" >> @top_srcdir@/src/libs/zbxdbhigh/dbschema.c
