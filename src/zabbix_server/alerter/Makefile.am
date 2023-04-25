## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxalerter.a

libzbxalerter_a_SOURCES = \
	alert.c \
	alert_manager.c \
	alert_syncer.c \
	alerter.c \
	alerter.h \
	alerter_protocol.c \
	alerter_protocol.h

libzbxalerter_a_CFLAGS = \
	$(LIBXML2_CFLAGS) \
	$(TLS_CFLAGS)
