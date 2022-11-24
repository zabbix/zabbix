## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxcachehistory.a

libzbxcachehistory_a_SOURCES = \
	dbcache.c

libzbxcachehistory_a_CFLAGS = \
	-I$(top_srcdir)/src/zabbix_server/ \
	$(TLS_CFLAGS) \
	$(TEST_FLAGS)
