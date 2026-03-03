## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxpoller_proxy.a

libzbxpoller_proxy_a_SOURCES = \
	checks_internal_proxy.c \
	poller_proxy.h

libzbxpoller_proxy_a_CFLAGS = \
	$(TLS_CFLAGS) \
	$(LIBEVENT_CFLAGS)
