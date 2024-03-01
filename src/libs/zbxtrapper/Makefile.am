## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxtrapper.a

libzbxtrapper_a_SOURCES = \
	active.c \
	active.h \
	trapper_auth.c \
	trapper_auth.h \
	nodecommand.c \
	nodecommand.h \
	trapper_preproc.c \
	trapper_preproc.h \
	trapper_expressions_evaluate.c \
	trapper_expressions_evaluate.h \
	trapper_item_test.c \
	trapper_item_test.h \
	trapper.c \
	trapper_request.h

libzbxtrapper_a_CFLAGS = \
	$(LIBXML2_CFLAGS) \
	$(TLS_CFLAGS)
