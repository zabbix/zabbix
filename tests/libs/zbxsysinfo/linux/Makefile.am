if AGENT
noinst_PROGRAMS = KERNEL_MAXPROC

KERNEL_MAXPROC_SOURCES = \
	KERNEL_MAXPROC.c \
	../../../../src/zabbix_agent/stats.c \
	../../../../src/zabbix_agent/cpustat.c \
	../../../../src/zabbix_agent/diskdevices.c \
	../../../../src/zabbix_agent/vmstats.c \
	../../../../src/zabbix_agent/logfiles.c \
	../../../../src/zabbix_agent/zbxconf.c \
	../../../../src/zabbix_agent/procstat.c \
	../../../zbxmocktest.h

KERNEL_MAXPROC_LDADD = \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspecsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspechostnamesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/agent/libagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
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
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/libs/zbxself/libzbxself.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a

KERNEL_MAXPROC_LDADD += @AGENT_LIBS@

KERNEL_MAXPROC_CFLAGS = -I@top_srcdir@/tests
endif
