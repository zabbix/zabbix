## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxalerter.a

libzbxalerter_a_SOURCES = \
	alert.c \
	alert_manager.c \
	alert_syncer.c \
	alerter.c \
	alerter_defs.h \
	alerter_internal.h \
	alerter_protocol.c \
	alerter_protocol.h \
	oauth.c

libzbxalerter_a_CFLAGS = \
	$(LIBXML2_CFLAGS) \
	$(TLS_CFLAGS)
