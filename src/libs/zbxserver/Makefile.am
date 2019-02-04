## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxserver.a libzbxserver_server.a libzbxserver_proxy.a

libzbxserver_a_SOURCES = \
	evalfunc.c evalfunc.h \
	expression.c \
	macrofunc.c macrofunc.h \
	zabbix_stats.c zabbix_stats.h

libzbxserver_server_a_SOURCES = \
	zabbix_stats_server.c zabbix_stats.h

libzbxserver_proxy_a_SOURCES = \
	zabbix_stats_proxy.c zabbix_stats.h

libzbxserver_a_CFLAGS = -I@top_srcdir@/src/libs/zbxdbcache @LIBXML2_CFLAGS@

libzbxserver_server_a_CFLAGS = -I@top_srcdir@/src/libs/zbxdbcache
