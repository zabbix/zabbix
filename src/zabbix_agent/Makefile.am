## Process this file with automake to produce Makefile.in

agents_ldadd = \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libzbxsysinfo2.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxnet/libzbxnet.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a

bin_PROGRAMS = \
	zabbix_agent \
	zabbix_agentd

zabbix_agent_SOURCES = \
	zabbix_agent.c zabbix_agent.h \
	zbxconf.c zbxconf.h

zabbix_agent_LDADD = $(agents_ldadd)

zabbix_agent_CPPFLAGS = $(LDAP_CPPFLAGS)

zabbix_agent_LDFLAGS = $(LDAP_LDFLAGS)

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

zabbix_agentd_LDADD = $(agents_ldadd)

zabbix_agentd_CPPFLAGS = -DZABBIX_DAEMON $(LDAP_CPPFLAGS)

zabbix_agentd_LDFLAGS = $(LDAP_LDFLAGS)
