## Process this file with automake to produce Makefile.in

sbin_PROGRAMS = zabbix_sender

zabbix_sender_SOURCES = zabbix_sender.c

zabbix_sender_LDADD = \
	../libs/zbxcommon/libzbxcommon.a \
	../libs/zbxlog/libzbxlog.a \
	../libs/zbxsys/libzbxsys.a \
	../libs/zbxcrypto/libzbxcrypto.a \
	../libs/zbxconf/libzbxconf.a \
	../libs/zbxcomms/libzbxcomms.a
