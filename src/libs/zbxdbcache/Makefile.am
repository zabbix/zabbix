## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxdbcache.a

libzbxdbcache_a_SOURCES = \
	dbcache.c \
	dbconfig.c \
	dbconfig.h \
	dbconfig_dump.c \
	dbconfig_maintenance.c \
	dbsync.c \
	dbsync.h \
	valuecache.c \
	valuecache.h \
	user_macro.c \
	user_macro.h

libzbxdbcache_a_CFLAGS = \
	-I$(top_srcdir)/src/zabbix_server/ \
	$(TLS_CFLAGS) \
	$(TEST_FLAGS)
