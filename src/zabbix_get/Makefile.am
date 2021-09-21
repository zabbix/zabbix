## Process this file with automake to produce Makefile.in

bin_PROGRAMS = zabbix_get

zabbix_get_SOURCES = \
	zabbix_get.c

zabbix_get_LDADD = \
	$(top_builddir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_builddir)/src/libs/zbxconf/libzbxconf.a \
	$(top_builddir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_builddir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_builddir)/src/libs/zbxlog/libzbxlog.a \
	$(top_builddir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_builddir)/src/libs/zbxsys/libzbxsys.a \
	$(top_builddir)/src/libs/zbxnix/libzbxnix.a \
	$(top_builddir)/src/libs/zbxcompress/libzbxcompress.a\
	$(ZBXGET_LIBS)

zabbix_get_LDFLAGS = $(ZBXGET_LDFLAGS)
