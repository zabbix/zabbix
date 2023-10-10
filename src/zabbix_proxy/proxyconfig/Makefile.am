## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxproxyconfig.a

libzbxproxyconfig_a_SOURCES = \
	proxyconfig.c \
	proxyconfig.h

libzbxproxyconfig_a_CFLAGS = \
	-I$(top_srcdir)/src/zabbix_proxy \
	$(TLS_CFLAGS)
