## Process this file with automake to produce Makefile.in

bin_PROGRAMS = zabbix_get

zabbix_get_SOURCES = zabbix_get.c

zabbix_get_LDADD = \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a
	 
zabbix_get_LDADD += @ZBXGET_LIBS@

zabbix_get_LDFLAGS = @ZBXGET_LDFLAGS@
