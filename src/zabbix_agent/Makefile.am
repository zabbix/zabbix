## Process this file with automake to produce Makefile.in

SUBDIRS = \
	listener \
	active_checks \
	agent_conf \
	logfiles

EXTRA_DIST = \
	eventlog

sbin_PROGRAMS = zabbix_agentd

zabbix_agentd_SOURCES = zabbix_agentd.c

zabbix_agentd_LDADD = \
	active_checks/libzbxactive_checks.a \
	listener/libzbxlistener.a \
	agent_conf/libagent_conf.a \
	logfiles/libzbxlogfiles.a \
	$(top_builddir)/src/libs/zbxsysinfo/libzbxagentsysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/$(ARCH)/libfunclistsysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/$(ARCH)/libspechostnamesysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/agent/libagentsysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_builddir)/src/libs/zbxsysinfo/$(ARCH)/libspecsysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_builddir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_builddir)/src/libs/zbxsysinfo/alias/libalias.a \
	$(top_builddir)/src/libs/zbxlog/libzbxlog.a \
	$(top_builddir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_builddir)/src/libs/zbxthreads/libzbxthreads.a \
	$(top_builddir)/src/libs/zbxmutexs/libzbxmutexs.a \
	$(top_builddir)/src/libs/zbxprof/libzbxprof.a \
	$(top_builddir)/src/libs/zbxnix/libzbxnix.a \
	$(top_builddir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_builddir)/src/libs/zbxcommshigh/libzbxcommshigh.a \
	$(top_builddir)/src/libs/zbxconf/libzbxconf.a \
	$(top_builddir)/src/libs/zbxjson/libzbxjson.a \
	$(top_builddir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_builddir)/src/libs/zbxvariant/libzbxvariant.a \
	$(top_builddir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_builddir)/src/libs/zbxgetopt/libzbxgetopt.a \
	$(top_builddir)/src/libs/zbxhash/libzbxhash.a \
	$(top_builddir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_builddir)/src/libs/zbxexec/libzbxexec.a \
	$(top_builddir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_builddir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_builddir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_builddir)/src/libs/zbxfile/libzbxfile.a \
	$(top_builddir)/src/libs/zbxip/libzbxip.a \
	$(top_builddir)/src/libs/zbxtime/libzbxtime.a \
	$(top_builddir)/src/libs/zbxnum/libzbxnum.a \
	$(top_builddir)/src/libs/zbxstr/libzbxstr.a \
	$(top_builddir)/src/libs/zbxparam/libzbxparam.a \
	$(top_builddir)/src/libs/zbxexpr/libzbxexpr.a \
	$(AGENT_LIBS)

zabbix_agentd_LDFLAGS = $(AGENT_LDFLAGS)

zabbix_agentd_CFLAGS = \
	-DZABBIX_DAEMON \
	-DDEFAULT_CONFIG_FILE="\"$(AGENT_CONFIG_FILE)\"" \
	-DDEFAULT_LOAD_MODULE_PATH="\"$(LOAD_MODULE_PATH)\"" \
	$(TLS_CFLAGS)

install-data-hook:
	$(MKDIR_P) "$(DESTDIR)$(AGENT_CONFIG_FILE).d"
	$(MKDIR_P) "$(DESTDIR)$(LOAD_MODULE_PATH)"
	test -f "$(DESTDIR)$(AGENT_CONFIG_FILE)" || cp "../../conf/zabbix_agentd.conf" "$(DESTDIR)$(AGENT_CONFIG_FILE)"
