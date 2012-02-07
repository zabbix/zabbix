## Process this file with automake to produce Makefile.in

agents_ldadd = \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspecsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a

agents_ldadd += @AGENT_LIBS@

sbin_PROGRAMS = \
	zabbix_agent \
	zabbix_agentd

zabbix_agent_SOURCES = \
	zabbix_agent.c \
	stats.c stats.h \
	cpustat.c cpustat.h \
	diskdevices.c diskdevices.h \
	perfstat.c perfstat.h \
	vmstats.c vmstats.h \
	zbxconf.c zbxconf.h

zabbix_agent_LDADD = $(agents_ldadd)

zabbix_agent_LDFLAGS = @AGENT_LDFLAGS@

zabbix_agentd_SOURCES = \
	active.c active.h \
	stats.c stats.h \
	cpustat.c cpustat.h \
	diskdevices.c diskdevices.h \
	perfstat.c perfstat.h \
	vmstats.c vmstats.h \
	logfiles.c logfiles.h \
	zabbix_agentd.c \
	zbxconf.c zbxconf.h \
	listener.c listener.h \
	eventlog.c eventlog.h

zabbix_agentd_LDADD = $(agents_ldadd)

zabbix_agentd_LDFLAGS = @AGENT_LDFLAGS@

zabbix_agentd_CFLAGS = \
	-DZABBIX_DAEMON
