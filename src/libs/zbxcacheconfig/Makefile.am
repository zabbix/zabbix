## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxcacheconfig.a

libzbxcacheconfig_a_SOURCES = \
	dbhistoryconfig.c \
	dbconfig.c \
	dbconfig.h \
	dbconfig_dump.c \
	dbconfig_maintenance.c \
	dbsync.c \
	dbsync.h \
	lld_macro.c \
	user_macro.c \
	user_macro.h

libzbxcacheconfig_a_CFLAGS = \
	-I$(top_srcdir)/src/zabbix_server/ \
	$(TLS_CFLAGS) \
	$(TEST_FLAGS)
