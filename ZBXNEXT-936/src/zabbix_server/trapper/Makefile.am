## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxtrapper.a

libzbxtrapper_a_SOURCES = \
	active.c active.h \
	trapper.c trapper.h \
	nodecommand.c nodecommand.h \
	proxycommand.c proxycommand.h \
	proxyconfig.c proxyconfig.h \
	proxydiscovery.c proxydiscovery.h \
	proxyautoreg.c proxyautoreg.h \
	proxyhosts.c proxyhosts.h

libzbxtrapper_a_CFLAGS = -I@top_srcdir@/src/zabbix_server/scripts/
