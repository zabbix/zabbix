## Process this file with automake to produce Makefile.in

SUBDIRS = \
	logfiles
	
EXTRA_DIST = \
	eventlog.c \
	eventlog.h \
	perfstat.c \
	perfstat.h

sbin_PROGRAMS = zabbix_agentd

noinst_LIBRARIES = libzbxagent.a

libzbxagent_a_SOURCES = \
	active.c \
	active.h \
	cpustat.c \
	cpustat.h \
	diskdevices.c \
	diskdevices.h \
	listener.c \
	listener.h \
	metrics.h \
	procstat.c \
	procstat.h \
	stats.c \
	stats.h \
	vmstats.c \
	vmstats.h \
	zbxconf.c \
	zbxconf.h \
	zbxkstat.c \
	zbxkstat.h

libzbxagent_a_CFLAGS = \
	-DZABBIX_DAEMON

zabbix_agentd_SOURCES = zabbix_agentd.c

zabbix_agentd_LDADD = \
	libzbxagent.a \
	logfiles/libzbxlogfiles.a \
	$(top_builddir)/src/libs/zbxsysinfo/libzbxagentsysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/$(ARCH)/libspecsysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/$(ARCH)/libspechostnamesysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/agent/libagentsysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_builddir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_builddir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_builddir)/src/libs/zbxlog/libzbxlog.a \
	$(top_builddir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_builddir)/src/libs/zbxsys/libzbxsys.a \
	$(top_builddir)/src/libs/zbxnix/libzbxnix.a \
	$(top_builddir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_builddir)/src/libs/zbxcommshigh/libzbxcommshigh.a \
	$(top_builddir)/src/libs/zbxconf/libzbxconf.a \
	$(top_builddir)/src/libs/zbxjson/libzbxjson.a \
	$(top_builddir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_builddir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_builddir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_builddir)/src/libs/zbxexec/libzbxexec.a \
	$(top_builddir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_builddir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_builddir)/src/libs/zbxhttp/libzbxhttp.a \
	$(AGENT_LIBS)

zabbix_agentd_LDFLAGS = $(AGENT_LDFLAGS)

zabbix_agentd_CFLAGS = \
	-DZABBIX_DAEMON \
	-DDEFAULT_CONFIG_FILE="\"$(AGENT_CONFIG_FILE)\"" \
	-DDEFAULT_LOAD_MODULE_PATH="\"$(LOAD_MODULE_PATH)\""

install-data-hook:
	$(MKDIR_P) "$(DESTDIR)$(AGENT_CONFIG_FILE).d"
	$(MKDIR_P) "$(DESTDIR)$(LOAD_MODULE_PATH)"
	test -f "$(DESTDIR)$(AGENT_CONFIG_FILE)" || cp "../../conf/zabbix_agentd.conf" "$(DESTDIR)$(AGENT_CONFIG_FILE)"
