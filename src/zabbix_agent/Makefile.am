## Process this file with automake to produce Makefile.in

AM_LDFLAGS = $(LDAP_LFLAGS)

LDADD = \
	../libs/zbxsysinfo/libzbxsysinfo.a \
	../libs/zbxsysinfo/$(ARCH)/libzbxsysinfo2.a \
	../libs/zbxlog/libzbxlog.a \
	../libs/zbxsys/libzbxsys.a \
	../libs/zbxnix/libzbxnix.a \
	../libs/zbxnet/libzbxnet.a \
	../libs/zbxconf/libzbxconf.a \
	../libs/zbxcommon/libzbxcommon.a \
	../libs/zbxcrypto/libzbxcrypto.a $(LDAP_LIBS)

bin_PROGRAMS = \
	zabbix_agent \
	zabbix_agentd

zabbix_agent_SOURCES = \
	zabbix_agent.c zabbix_agent.h \
	zbxconf.c zbxconf.h

zabbix_agentd_SOURCES = \
	active.c active.h \
	stats.c stats.h \
	cpustat.c cpustat.h \
	diskdevices.c diskdevices.h \
	interfaces.c interfaces.h \
	logfiles.c logfiles.h \
	zabbix_agentd.c \
	zbxconf.c zbxconf.h \
	listener.c listener.h \
	eventlog.c eventlog.h

zabbix_agentd_CPPFLAGS = -DZABBIX_DAEMON
