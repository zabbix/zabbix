## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxeval.a

libzbxeval_a_CFLAGS = \
	$(TLS_CFLAGS)

if HAVE_LIBXML2
libzbxeval_a_CFLAGS +=	$(LIBXML2_CFLAGS)
endif

libzbxeval_a_SOURCES = \
	count_pattern.c \
	count_pattern.h \
	parse.c \
	execute.c \
	misc.c \
	query.c \
	calc.c \
	eval.h
