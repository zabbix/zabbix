## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxcachehistory_server.a

libzbxcachehistory_server_a_SOURCES = \
	cachehistory_server.c \
	cachehistory_server.h \
	trigger_eval.c

libzbxcachehistory_server_a_CFLAGS = \
	-I$(top_srcdir)/src/zabbix_server/ \
	$(TLS_CFLAGS) \
	$(TEST_FLAGS)
