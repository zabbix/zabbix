## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxipmi.a

libzbxipmi_a_SOURCES = \
	checks_ipmi.c \
	checks_ipmi.h \
	ipmi_manager.c \
	ipmi_poller.c \
	ipmi_protocol.c \
	ipmi_protocol.h \
	ipmi.c \
	ipmi.h

libzbxipmi_a_CFLAGS = \
	$(OPENIPMI_CFLAGS) \
	$(TLS_CFLAGS) \
	$(ARES_CFLAGS)
