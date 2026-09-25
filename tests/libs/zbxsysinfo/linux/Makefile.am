include ../../Makefile.include

if AGENT
AGENT_tests = \
	net_if_discovery \
	net_if_get \
	vfs_fs_discovery \
	system_process \
	kernel_maxproc \
	system_cpu_switches \
	system_sw_packages_get \
	kernel_maxfiles \
	system_cpu_intr \
	system_boottime \
	net_if_total \
	net_if_in \
	net_if_out \
	system_hw_chassis \
	system_sw_software
endif

noinst_PROGRAMS = $(AGENT_tests)

if AGENT
COMMON_SRC_FILES = \
	../../../zbxmocktest.h

COMMON_LIB_FILES = \
	$(AGENT_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

COMMON_COMPILER_FLAGS = -DZABBIX_DAEMON -I@top_srcdir@/tests $(CMOCKA_CFLAGS) $(YAML_CFLAGS) $(TLS_CFLAGS)

# net_if_discovery
net_if_discovery_SOURCES = \
	net_if_discovery.c \
	$(COMMON_SRC_FILES)

net_if_discovery_LDADD = \
	$(COMMON_LIB_FILES)

net_if_discovery_LDADD += @AGENT_LIBS@

net_if_discovery_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

net_if_discovery_CFLAGS = $(COMMON_COMPILER_FLAGS)

# net_if_get
net_if_get_SOURCES = \
	net_if_get.c \
	$(COMMON_SRC_FILES)

net_if_get_LDADD = \
	$(COMMON_LIB_FILES)

net_if_get_LDADD += @AGENT_LIBS@

net_if_get_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

net_if_get_CFLAGS = $(COMMON_COMPILER_FLAGS)

# vfs_fs_discovery
vfs_fs_discovery_SOURCES = \
	vfs_fs_discovery.c \
	$(COMMON_SRC_FILES)

vfs_fs_discovery_LDADD = \
	$(COMMON_LIB_FILES)

vfs_fs_discovery_LDADD += @AGENT_LIBS@

vfs_fs_discovery_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

vfs_fs_discovery_CFLAGS = $(COMMON_COMPILER_FLAGS)

# system_process
system_process_SOURCES = \
	system_process.c \
	$(COMMON_SRC_FILES)

system_process_LDADD = \
	$(COMMON_LIB_FILES)

system_process_LDADD += @AGENT_LIBS@

system_process_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

system_process_CFLAGS = $(COMMON_COMPILER_FLAGS)

# kernel_maxproc
kernel_maxproc_SOURCES = \
	kernel_common.c \
	kernel_maxproc.c \
	$(COMMON_SRC_FILES)

kernel_maxproc_LDADD = \
	$(COMMON_LIB_FILES)

kernel_maxproc_LDADD += @AGENT_LIBS@

kernel_maxproc_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

kernel_maxproc_CFLAGS = $(COMMON_COMPILER_FLAGS)

# system_cpu_switches
system_cpu_switches_SOURCES = \
	system_cpu_switches.c \
	$(COMMON_SRC_FILES)

system_cpu_switches_LDADD = \
	$(COMMON_LIB_FILES)

system_cpu_switches_LDADD += @AGENT_LIBS@

system_cpu_switches_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

system_cpu_switches_CFLAGS = $(COMMON_COMPILER_FLAGS)

# system_sw_packages_get
system_sw_packages_get_SOURCES = \
	system_sw_packages_get.c \
	$(COMMON_SRC_FILES)

system_sw_packages_get_LDADD = \
	$(COMMON_LIB_FILES)

system_sw_packages_get_LDADD += @AGENT_LIBS@

system_sw_packages_get_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

system_sw_packages_get_CFLAGS = $(COMMON_COMPILER_FLAGS)

# kernel_maxfiles
kernel_maxfiles_SOURCES = \
	kernel_common.c \
	kernel_maxfiles.c \
	$(COMMON_SRC_FILES)

kernel_maxfiles_LDADD = \
	$(COMMON_LIB_FILES)

kernel_maxfiles_LDADD += @AGENT_LIBS@

kernel_maxfiles_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

kernel_maxfiles_CFLAGS = $(COMMON_COMPILER_FLAGS)

# system_cpu_intr
system_cpu_intr_SOURCES = \
	system_cpu_intr.c \
	$(COMMON_SRC_FILES)

system_cpu_intr_LDADD = \
	$(COMMON_LIB_FILES)

system_cpu_intr_LDADD += @AGENT_LIBS@

system_cpu_intr_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

system_cpu_intr_CFLAGS = $(COMMON_COMPILER_FLAGS)

# system_boottime
system_boottime_SOURCES = \
	system_boottime.c \
	$(COMMON_SRC_FILES)

system_boottime_LDADD = \
	$(COMMON_LIB_FILES)

system_boottime_LDADD += @AGENT_LIBS@

system_boottime_LDFLAGS = @AGENT_LDFLAGS@ $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)

system_boottime_CFLAGS = $(COMMON_COMPILER_FLAGS)

# net_if_total
net_if_total_SOURCES = \
	net_if_common.c \
	net_if_total.c \
	$(COMMON_SRC_FILES)

net_if_total_LDADD = $(COMMON_LIB_FILES) @AGENT_LIBS@
net_if_total_LDFLAGS = $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)
net_if_total_CFLAGS = $(COMMON_COMPILER_FLAGS)

# net_if_in
net_if_in_SOURCES = \
	net_if_common.c \
	net_if_in.c \
	$(COMMON_SRC_FILES)

net_if_in_LDADD = $(COMMON_LIB_FILES) @AGENT_LIBS@
net_if_in_LDFLAGS = $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)
net_if_in_CFLAGS = $(COMMON_COMPILER_FLAGS)

# net_if_out
net_if_out_SOURCES = \
	net_if_common.c \
	net_if_out.c \
	$(COMMON_SRC_FILES)

net_if_out_LDADD = $(COMMON_LIB_FILES) @AGENT_LIBS@
net_if_out_LDFLAGS = $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)
net_if_out_CFLAGS = $(COMMON_COMPILER_FLAGS)

# system_hw_chassis
system_hw_chassis_SOURCES = \
	system_hw_chassis.c \
	$(COMMON_SRC_FILES)

system_hw_chassis_LDADD = $(COMMON_LIB_FILES) @AGENT_LIBS@
system_hw_chassis_LDFLAGS = $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) $(TLS_LDFLAGS)
system_hw_chassis_CFLAGS = $(COMMON_COMPILER_FLAGS)

# system_sw_software
system_sw_software_SOURCES = \
	system_sw_software.c \
	$(COMMON_SRC_FILES)

system_sw_software_LDADD = $(COMMON_LIB_FILES) @AGENT_LIBS@

system_sw_software_WRAP_FUNCS = \
	-Wl,--wrap=fopen \
	-Wl,--wrap=uname

system_sw_software_LDFLAGS = @AGENT_LDFLAGS@ $(system_sw_software_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS) \
	 $(TLS_LDFLAGS)

system_sw_software_CFLAGS = $(COMMON_COMPILER_FLAGS)

endif
