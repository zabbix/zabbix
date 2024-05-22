## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxstats_server.a

libzbxstats_server_a_SOURCES = \
	stats_server.h \
	stats_server.c

libzbxstats_server_a_CFLAGS = \
	-I$(top_srcdir)/src/libs/zbxcacheconfig \
	-I$(top_srcdir)/src/libs/zbxcachehistory \
	-I$(top_srcdir)/src/libs/zbxcachevalue \
	$(TLS_CFLAGS)
