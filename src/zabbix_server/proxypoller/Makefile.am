## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxproxypoller.a

libzbxproxypoller_a_SOURCES = \
	proxypoller.c \
	proxypoller.h

libzbxproxypoller_a_CFLAGS = \
	-I$(top_srcdir)/src/zabbix_server \
	$(TLS_CFLAGS)
