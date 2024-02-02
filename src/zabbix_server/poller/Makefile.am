## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxpoller_server.a libzbxpoller_proxy.a

libzbxpoller_server_a_SOURCES = \
	checks_internal_server.h \
	checks_internal_server.c

libzbxpoller_proxy_a_SOURCES = \
	checks_internal_proxy.h \
	checks_internal_proxy.c

libzbxpoller_a_CFLAGS = \
	-I$(top_srcdir)/src/libs/zbxsysinfo/simple \
	-I$(top_srcdir)/src/libs/zbxcacheconfig \
	-I$(top_srcdir)/src/libs/zbxcachehistory \
	-I$(top_srcdir)/src/libs/zbxcachevalue \
	$(SNMP_CFLAGS) \
	$(LIBXML2_CFLAGS) \
	$(TLS_CFLAGS) \
	$(LIBEVENT_CFLAGS)

libzbxpoller_server_a_CFLAGS = \
	-I$(top_srcdir)/src/libs/zbxcacheconfig \
	-I$(top_srcdir)/src/libs/zbxcachehistory \
	-I$(top_srcdir)/src/libs/zbxcachevalue \
	$(TLS_CFLAGS)

libzbxpoller_proxy_a_CFLAGS = \
	$(TLS_CFLAGS)
