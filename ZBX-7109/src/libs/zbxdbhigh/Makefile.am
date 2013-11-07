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
	proxy.c \
	discovery.c \
	lld.c lld.h \
	lld_common.c \
	lld_item.c \
	lld_graph.c \
	lld_trigger.c

libzbxdbhigh_a_CFLAGS = -I@top_srcdir@/src/zabbix_server/
