## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = 			\
	libzbxcachehistory.a 		\
	libzbxcachehistory_proxy.a

# common
libzbxcachehistory_a_SOURCES = \
	dbcache.c \
	dbcache.h

libzbxcachehistory_a_CFLAGS = \
	-I$(top_srcdir)/src/zabbix_server/ \
	$(TLS_CFLAGS) \
	$(TEST_FLAGS)

# proxy

libzbxcachehistory_proxy_a_SOURCES = \
	dbcache_proxy.c

libzbxcachehistory_proxy_a_CFLAGS = \
	$(TLS_CFLAGS) \
	$(TEST_FLAGS)
