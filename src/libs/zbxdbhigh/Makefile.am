## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxdbhigh.a

libzbxdbhigh_a_SOURCES = \
	action.c \
	graph.c \
	item.c \
	trigger.c \
	host.c \
	db.c

libzbxdbhigh_a_CPPFLAGS = -I@top_srcdir@/src/zabbix_server/ $(DB_CPPFLAGS)
