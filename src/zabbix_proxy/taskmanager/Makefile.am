## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxtaskmanager_proxy.a

libzbxtaskmanager_proxy_a_SOURCES = \
	server_tasks.c \
	taskmanager_proxy.c \
	taskmanager_proxy.h

libzbxtaskmanager_proxy_a_CFLAGS = \
	$(TLS_CFLAGS) \
	$(ARES_CFLAGS)
