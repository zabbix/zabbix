## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxtrapper_server.a

libzbxtrapper_server_a_SOURCES = \
	trapper_server.c \
	trapper_server.h \
	proxydata.c \
	proxydata.h \
	trapper_history_push.c \
	trapper_history_push.h

libzbxtrapper_server_a_CFLAGS = \
	-I$(top_srcdir)/src/zabbix_server \
	$(TLS_CFLAGS) \
	$(ARES_CFLAGS)
