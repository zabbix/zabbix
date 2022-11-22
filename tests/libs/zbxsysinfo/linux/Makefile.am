if AGENT
AGENT_tests = \
	NET_IF_DISCOVERY \
	VFS_FS_DISCOVERY \
	KERNEL_MAXPROC \
	SYSTEM_CPU_SWITCHES \
	KERNEL_MAXFILES \
	SYSTEM_CPU_INTR \
	SYSTEM_BOOTTIME \
	NET_IF_TOTAL \
	NET_IF_IN \
	NET_IF_OUT \
	SYSTEM_HW_CHASSIS
endif

noinst_PROGRAMS = $(AGENT_tests)

if AGENT
COMMON_SRC_FILES = \
	../../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(top_srcdir)/src/libs/zbxsysinfo/libzbxagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspecsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/$(ARCH)/libspechostnamesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/agent/libagentsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_httpmetrics.a \
	$(top_srcdir)/src/libs/zbxsysinfo/common/libcommonsysinfo_http.a \
	$(top_srcdir)/src/libs/zbxlog/libzbxlog.a \
	$(top_srcdir)/src/libs/zbxregexp/libzbxregexp.a \
	$(top_srcdir)/src/libs/zbxsys/libzbxsys.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/src/libs/zbxcomms/libzbxcomms.a \
	$(top_srcdir)/src/libs/zbxcompress/libzbxcompress.a \
	$(top_srcdir)/src/libs/zbxconf/libzbxconf.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxalgo/libzbxalgo.a \
	$(top_srcdir)/src/libs/zbxcommon/libzbxcommon.a \
	$(top_srcdir)/src/libs/zbxcrypto/libzbxcrypto.a \
	$(top_srcdir)/src/libs/zbxjson/libzbxjson.a \
	$(top_srcdir)/src/libs/zbxhttp/libzbxhttp.a \
	$(top_srcdir)/src/libs/zbxexec/libzbxexec.a \
	$(top_srcdir)/src/libs/zbxmodules/libzbxmodules.a \
	$(top_srcdir)/src/zabbix_agent/libzbxagent.a \
	$(top_srcdir)/src/libs/zbxnix/libzbxnix.a \
	$(top_srcdir)/tests/libzbxmocktest.a \
	$(top_srcdir)/tests/libzbxmockdata.a

COMMON_COMPILER_FLAGS = -DZABBIX_DAEMON -I@top_srcdir@/tests

NET_IF_DISCOVERY_SOURCES = \
	NET_IF_DISCOVERY.c \
	$(COMMON_SRC_FILES)

NET_IF_DISCOVERY_LDADD = \
	$(COMMON_LIB_FILES)

NET_IF_DISCOVERY_LDADD += @AGENT_LIBS@

NET_IF_DISCOVERY_LDFLAGS = @AGENT_LDFLAGS@

NET_IF_DISCOVERY_CFLAGS = $(COMMON_COMPILER_FLAGS)

VFS_FS_DISCOVERY_SOURCES = \
	VFS_FS_DISCOVERY.c \
	$(COMMON_SRC_FILES)

VFS_FS_DISCOVERY_LDADD = \
	$(COMMON_LIB_FILES)

VFS_FS_DISCOVERY_LDADD += @AGENT_LIBS@

VFS_FS_DISCOVERY_LDFLAGS = @AGENT_LDFLAGS@

VFS_FS_DISCOVERY_CFLAGS = $(COMMON_COMPILER_FLAGS)

KERNEL_MAXPROC_SOURCES = \
	KERNEL_COMMON.c \
	KERNEL_MAXPROC.c \
	$(COMMON_SRC_FILES)

KERNEL_MAXPROC_LDADD = \
	$(COMMON_LIB_FILES)

KERNEL_MAXPROC_LDADD += @AGENT_LIBS@

KERNEL_MAXPROC_LDFLAGS = @AGENT_LDFLAGS@

KERNEL_MAXPROC_CFLAGS = $(COMMON_COMPILER_FLAGS)

SYSTEM_CPU_SWITCHES_SOURCES = \
	SYSTEM_CPU_SWITCHES.c \
	$(COMMON_SRC_FILES)

SYSTEM_CPU_SWITCHES_LDADD = \
	$(COMMON_LIB_FILES)

SYSTEM_CPU_SWITCHES_LDADD += @AGENT_LIBS@

SYSTEM_CPU_SWITCHES_LDFLAGS = @AGENT_LDFLAGS@

SYSTEM_CPU_SWITCHES_CFLAGS = $(COMMON_COMPILER_FLAGS)

KERNEL_MAXFILES_SOURCES = \
	KERNEL_COMMON.c \
	KERNEL_MAXFILES.c \
	$(COMMON_SRC_FILES)

KERNEL_MAXFILES_LDADD = \
	$(COMMON_LIB_FILES)

KERNEL_MAXFILES_LDADD += @AGENT_LIBS@

KERNEL_MAXFILES_LDFLAGS = @AGENT_LDFLAGS@

KERNEL_MAXFILES_CFLAGS = $(COMMON_COMPILER_FLAGS)

SYSTEM_CPU_INTR_SOURCES = \
	SYSTEM_CPU_INTR.c \
	$(COMMON_SRC_FILES)

SYSTEM_CPU_INTR_LDADD = \
	$(COMMON_LIB_FILES)

SYSTEM_CPU_INTR_LDADD += @AGENT_LIBS@

SYSTEM_CPU_INTR_LDFLAGS = @AGENT_LDFLAGS@

SYSTEM_CPU_INTR_CFLAGS = $(COMMON_COMPILER_FLAGS)

SYSTEM_BOOTTIME_SOURCES = \
	SYSTEM_BOOTTIME.c \
	$(COMMON_SRC_FILES)

SYSTEM_BOOTTIME_LDADD = \
	$(COMMON_LIB_FILES)

SYSTEM_BOOTTIME_LDADD += @AGENT_LIBS@

SYSTEM_BOOTTIME_LDFLAGS = @AGENT_LDFLAGS@

SYSTEM_BOOTTIME_CFLAGS = $(COMMON_COMPILER_FLAGS)

#NET_IF_TOTAL	
NET_IF_TOTAL_SOURCES = \
	NET_IF_COMMON.c \
	NET_IF_TOTAL.c \
	$(COMMON_SRC_FILES)

NET_IF_TOTAL_LDADD = $(COMMON_LIB_FILES) @AGENT_LIBS@

NET_IF_TOTAL_CFLAGS = $(COMMON_COMPILER_FLAGS)

#NET_IF_IN
NET_IF_IN_SOURCES = \
	NET_IF_COMMON.c \
	NET_IF_IN.c \
	$(COMMON_SRC_FILES)

NET_IF_IN_LDADD = $(COMMON_LIB_FILES) @AGENT_LIBS@

NET_IF_IN_CFLAGS = $(COMMON_COMPILER_FLAGS)

#NET_IF_OUT
NET_IF_OUT_SOURCES = \
	NET_IF_COMMON.c \
	NET_IF_OUT.c \
	$(COMMON_SRC_FILES)

NET_IF_OUT_LDADD = $(COMMON_LIB_FILES) @AGENT_LIBS@

NET_IF_OUT_CFLAGS = $(COMMON_COMPILER_FLAGS)

#SYSTEM_HW_CHASSIS
SYSTEM_HW_CHASSIS_SOURCES = \
	SYSTEM_HW_CHASSIS.c \
	$(COMMON_SRC_FILES)

SYSTEM_HW_CHASSIS_LDADD = $(COMMON_LIB_FILES) @AGENT_LIBS@

SYSTEM_HW_CHASSIS_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif
