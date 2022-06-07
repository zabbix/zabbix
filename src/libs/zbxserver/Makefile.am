## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxserver.a

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
	get_host_from_event.c \
	get_host_from_event.h \
	zabbix_users.c \
	zabbix_users.h \
	calc_checks_eval.c \
	evaluate_simple.c

libzbxserver_a_CFLAGS = -I$(top_srcdir)/src/libs/zbxdbcache $(LIBXML2_CFLAGS)
