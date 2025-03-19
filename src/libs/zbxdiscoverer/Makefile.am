## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxdiscoverer.a

libzbxdiscoverer_a_SOURCES = \
	discoverer.c \
	discoverer_queue.c \
	discoverer_queue.h \
	discoverer_job.c \
	discoverer_job.h \
	discoverer_taskprep.c \
	discoverer_taskprep.h \
	discoverer_async.c \
	discoverer_async.h \
	async_tcpsvc.c \
	async_tcpsvc.h \
	async_telnet.c \
	async_telnet.h \
	async_http.c \
	async_http.h \
	discoverer_int.h

libzbxdiscoverer_a_CFLAGS = \
	$(TLS_CFLAGS) \
	$(ARES_CFLAGS)
