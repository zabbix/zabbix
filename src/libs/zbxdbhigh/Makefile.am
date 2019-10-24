## Process this file with automake to produce Makefile.in

if DBSCHEMA
dbschema.c: $(top_srcdir)/create/src/schema.tmpl
	$(top_srcdir)/create/bin/gen_schema.pl c > dbschema.c
endif

noinst_LIBRARIES = libzbxdbhigh.a

libzbxdbhigh_a_SOURCES = \
	db.c \
	dbschema.c \
	discovery.c \
	event.c \
	export.c \
	host.c \
	item.c \
	itservices.c \
	lld_macro.c \
	maintenance.c \
	proxy.c \
	template_item.c \
	template.h \
	trigger.c

libzbxdbhigh_a_CFLAGS = \
	-I$(top_srcdir)/src/zabbix_server/
