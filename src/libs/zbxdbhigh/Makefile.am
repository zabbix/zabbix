## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxdbhigh.a

libzbxdbhigh_a_SOURCES = \
	db.c \
	item.c \
	maintenance.c \
	proxy.c \
	trigger.c \
	tag.c \
	item_param.c \
	lld_override.c \
	mediatype.c

libzbxdbhigh_a_CFLAGS = \
	-I$(top_srcdir)/src/zabbix_server/ \
	$(TLS_CFLAGS)
