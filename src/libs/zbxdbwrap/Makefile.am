## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxdbwrap.a

libzbxdbwrap_a_SOURCES = \
	proxy.c \
	event.c \
	template_item.c \
	template_item_audit.c \
	template.h \
	trigger.c \
	trigger_dep_linking.c \
	trigger_dep_linking.h \
	trigger_linking.c \
	trigger_linking.h \
	graph_linking.c \
	graph_linking.h \
	host.c \
	user.c \
	permission.c \
	macro_ctx_trigger.c

libzbxdbwrap_a_CFLAGS = \
	-I$(top_srcdir)/src/zabbix_server/ \
	$(TLS_CFLAGS)
