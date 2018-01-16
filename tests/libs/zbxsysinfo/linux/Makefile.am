if AGENT
AGENT_tests = \
	NET_IF_TOTAL \
	NET_IF_IN \
	NET_IF_OUT
endif

noinst_PROGRAMS = $(AGENT_tests)

if AGENT
COMMON_SRC_FILES = \
	../../../../src/zabbix_agent/active.c ../../../../src/zabbix_agent/active.h \
	../../../../src/zabbix_agent/stats.c \
	../../../../src/zabbix_agent/cpustat.c \
	../../../../src/zabbix_agent/diskdevices.c \
	../../../../src/zabbix_agent/vmstats.c \
	../../../../src/zabbix_agent/logfiles.c \
	../../../../src/zabbix_agent/zbxconf.c \
	../../../../src/zabbix_agent/listener.c \
	../../../../src/zabbix_agent/procstat.c \
	../../../zbxmocktest.h

COMMON_LIB_FILES = \
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
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a

COMMON_COMPILER_FLAGS = -DZABBIX_DAEMON -I@top_srcdir@/tests

#NET_IF_TOTAL	
NET_IF_TOTAL_SOURCES = \
	NET_IF_TOTAL.c \
	$(COMMON_SRC_FILES)

NET_IF_TOTAL_LDADD = $(COMMON_LIB_FILES) @AGENT_LIBS@

NET_IF_TOTAL_CFLAGS = $(COMMON_COMPILER_FLAGS)

#NET_IF_IN
NET_IF_IN_SOURCES = \
	NET_IF_IN.c \
	$(COMMON_SRC_FILES)

NET_IF_IN_LDADD = $(COMMON_LIB_FILES) @AGENT_LIBS@

NET_IF_IN_CFLAGS = $(COMMON_COMPILER_FLAGS)

#NET_IF_OUT
NET_IF_OUT_SOURCES = \
	NET_IF_OUT.c \
	$(COMMON_SRC_FILES)

NET_IF_OUT_LDADD = $(COMMON_LIB_FILES) @AGENT_LIBS@

NET_IF_OUT_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif
