## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxdbhigh.a

libzbxdbhigh_a_SOURCES = \
	host.c \
	odbc.c \
	db.c \
	dbschema.c \
	proxy.c \
	discovery.c \
	lld.c lld.h \
	lld_common.c \
	lld_item.c \
	lld_graph.c \
	lld_trigger.c \
	lld_host.c \
	itservices.c \
	template_item.c \
	template.h \
	trigger.c

libzbxdbhigh_a_CFLAGS = -I@top_srcdir@/src/zabbix_server/ @UNIXODBC_CFLAGS@
