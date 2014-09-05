## Process this file with automake to produce Makefile.in
EXTRA_DIST = \
	eventlog.c eventlog.h \
	perfstat.c perfstat.h

agents_ldadd = \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspecsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspechostnamesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/agent/libagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a

agents_ldadd += @AGENT_LIBS@

sbin_PROGRAMS = \
	zabbix_agent \
	zabbix_agentd

zabbix_agent_SOURCES = \
	zabbix_agent.c \
	stats.c stats.h \
	cpustat.c cpustat.h \
	diskdevices.c diskdevices.h \
	vmstats.c vmstats.h \
	zbxconf.c zbxconf.h

zabbix_agent_LDADD = $(agents_ldadd)

zabbix_agent_LDFLAGS = @AGENT_LDFLAGS@

zabbix_agentd_SOURCES = \
	active.c active.h \
	stats.c stats.h \
	cpustat.c cpustat.h \
	diskdevices.c diskdevices.h \
	vmstats.c vmstats.h \
	logfiles.c logfiles.h \
	zabbix_agentd.c \
	zbxconf.c zbxconf.h \
	listener.c listener.h

zabbix_agentd_LDADD = $(agents_ldadd)

zabbix_agentd_LDFLAGS = @AGENT_LDFLAGS@

zabbix_agentd_CFLAGS = \
	-DZABBIX_DAEMON \
	-DSYSCONFDIR="\"@sysconfdir@\"" \
	-DLIBDIR="\"@libdir@\""

zabbix_agent_CFLAGS = \
	-DSYSCONFDIR="\"@sysconfdir@\"" \
	-DLIBDIR="\"@libdir@\""

AGENT_CONFIG = zabbix_agent.conf
AGENTD_CONFIG = zabbix_agentd.conf
AGENT_INCLUDE_DIR = $(DESTDIR)@sysconfdir@/$(AGENT_CONFIG).d
AGENTD_INCLUDE_DIR = $(DESTDIR)@sysconfdir@/$(AGENTD_CONFIG).d
MODULES_DIR = $(DESTDIR)@libdir@

install-data-hook:
	$(MKDIR_P) "$(AGENT_INCLUDE_DIR)"
	$(MKDIR_P) "$(AGENTD_INCLUDE_DIR)"
	$(MKDIR_P) "$(MODULES_DIR)"
	test -f "$(DESTDIR)@sysconfdir@/$(AGENT_CONFIG)" || cp "../../conf/$(AGENT_CONFIG)" "$(DESTDIR)@sysconfdir@"
	test -f "$(DESTDIR)@sysconfdir@/$(AGENTD_CONFIG)" || cp "../../conf/$(AGENTD_CONFIG)" "$(DESTDIR)@sysconfdir@"
