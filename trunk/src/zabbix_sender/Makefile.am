## Process this file with automake to produce Makefile.in

bin_PROGRAMS = zabbix_sender

zabbix_sender_SOURCES = zabbix_sender.c

zabbix_sender_LDADD = \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a 

zabbix_sender_LDADD += @SENDER_LIBS@

zabbix_sender_LDFLAGS = @SENDER_LDFLAGS@

EXTRA_DIST = win32
