## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxtrapper_proxy.a

libzbxtrapper_proxy_a_SOURCES = \
	trapper_proxy.c \
	trapper_request.h

libzbxtrapper_proxy_a_CFLAGS = \
	-I$(top_srcdir)/src/zabbix_proxy \
	$(TLS_CFLAGS)
