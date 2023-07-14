## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = 			\
	libzbxdiscovery.a		\
	libzbxdiscovery_server.a	\
	libzbxdiscovery_proxy.a

# common
libzbxdiscovery_a_SOURCES = \
	discovery.c

libzbxdiscovery_a_CFLAGS = \
	-I$(top_srcdir)/src/zabbix_server/ \
	$(TLS_CFLAGS)

# server
libzbxdiscovery_server_a_SOURCES = \
	discovery_server.c

libzbxdiscovery_server_a_CFLAGS = \
	-I$(top_srcdir)/src/zabbix_server/ \
	$(TLS_CFLAGS)

# proxy
libzbxdiscovery_proxy_a_SOURCES = \
	discovery_proxy.c

libzbxdiscovery_proxy_a_CFLAGS = \
	$(TLS_CFLAGS)
