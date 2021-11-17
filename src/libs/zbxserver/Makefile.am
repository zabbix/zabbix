## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxserver.a libzbxserver_server.a libzbxserver_proxy.a

libzbxserver_a_SOURCES = \
	evalfunc_common.c \
	evalfunc_common.h \
	evalfunc.c \
	evalfunc.h \
	anomalystl.c \
	anomalystl.h \
	expression.c \
	expression.h \
	macrofunc.c \
	macrofunc.h \
	zabbix_stats.c \
	zabbix_stats.h \
	get_host_from_event.c \
	get_host_from_event.h \
	zabbix_users.c \
	zabbix_users.h \
	expression_eval.c

libzbxserver_server_a_SOURCES = \
	zabbix_stats.h \
	zabbix_stats_server.c

libzbxserver_proxy_a_SOURCES = \
	zabbix_stats.h \
	zabbix_stats_proxy.c

libzbxserver_a_CFLAGS = -I$(top_srcdir)/src/libs/zbxdbcache $(LIBXML2_CFLAGS)

libzbxserver_server_a_CFLAGS = -I$(top_srcdir)/src/libs/zbxdbcache
