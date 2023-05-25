## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxdbconfig.a

libzbxdbconfig_a_SOURCES = \
	dbconfig.c \
	dbconfig.h

libzbxdbconfig_a_CFLAGS = \
	-I$(top_srcdir)/src/libs/zbxcacheconfig \
	-I$(top_srcdir)/src/libs/zbxcachehistory \
	-I$(top_srcdir)/src/libs/zbxcachevalue \
	$(TLS_CFLAGS)
