## Process this file with automake to produce Makefile.in

if DBSCHEMA
dbschema.c: $(top_srcdir)/create/src/schema.tmpl
	$(top_srcdir)/create/bin/gen_schema.pl c > dbschema.c
endif

noinst_LIBRARIES = libzbxdbhigh.a

libzbxdbhigh_a_SOURCES = \
	host.c \
	db.c \
	dbschema.c \
	proxy.c \
	discovery.c \
	itservices.c \
	template_item.c \
	template.h \
	trigger.c \
	event.c \
	export.c \
	maintenance.c \
	item.c \
	lld_macro.c

libzbxdbhigh_a_CFLAGS = -I@top_srcdir@/src/zabbix_server/
