## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxhistory.a

libzbxhistory_a_CFLAGS = \
	$(TLS_CFLAGS)

libzbxhistory_a_SOURCES = \
	history.c history.h \
	history_elastic.c \
	history_elastic.h \
	history_sql.c \
	history_sql.h \
	history_clickhouse.c \
	history_clickhouse.h \
	history_curl.c \
	history_curl.h \
	history_option.c \
	history_option.h
