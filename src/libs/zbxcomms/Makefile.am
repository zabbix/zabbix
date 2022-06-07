## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxcomms.a

libzbxcomms_a_SOURCES = \
	comms.h \
	comms.c \
	telnet.c \
	tls.h \
	tls.c \
	tls_version.c

libzbxcomms_a_CFLAGS = \
		$(LIBXML2_CFLAGS) \
		$(TLS_CFLAGS)
