## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxlogfiles.a libzbxagent2logfiles.a

libzbxlogfiles_a_SOURCES = \
	logfiles.c logfiles.h \
	persistent_state.c persistent_state.h

libzbxlogfiles_a_CFLAGS = $(TLS_CFLAGS)

libzbxagent2logfiles_a_SOURCES = \
	logfiles.c logfiles.h \
	persistent_state.c persistent_state.h

libzbxagent2logfiles_a_CFLAGS = \
	$(TLS_CFLAGS) \
	-DWITH_AGENT2_METRICS
