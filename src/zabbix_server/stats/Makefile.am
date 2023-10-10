## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxserver_server.a

libzbxserver_server_a_SOURCES = \
	zabbix_stats.h \
	server_stats.c

libzbxserver_server_a_CFLAGS = \
	-I$(top_srcdir)/src/libs/zbxcacheconfig \
	-I$(top_srcdir)/src/libs/zbxcachehistory \
	-I$(top_srcdir)/src/libs/zbxcachevalue \
	$(TLS_CFLAGS)
