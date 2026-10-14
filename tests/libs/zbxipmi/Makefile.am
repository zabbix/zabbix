include ../Makefile.include

if HAVE_IPMI

BINARIES_tests = \
	zbx_ipmi_change_callbacks

noinst_PROGRAMS = $(BINARIES_tests)

COMMON_SRC_FILES = \
	../../zbxmocktest.h

# The tested functions are static in checks_ipmi.c, so the source file is compiled directly into
# each test binary instead of being linked from libzbxipmi.a (which would duplicate the symbols).
IPMI_LIBS = \
	$(SERVER_GROUPED_LIBS) \
	$(MOCK_DATA_DEPS) \
	$(MOCK_TEST_DEPS)

IPMI_COMPILER_FLAGS = \
	-I@top_srcdir@/tests \
	$(OPENIPMI_CFLAGS) \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)

# libzbxmockdata.a unconditionally pulls in zbxmockfile.o's filesystem mocking layer, which calls
# __real_*() counterparts of these functions; every cmocka test in this tree needs the matching
# --wrap flags to link, regardless of whether the test itself touches the filesystem.
MOCKDATA_WRAP_FUNCS = \
	-Wl,--wrap=fopen \
	-Wl,--wrap=fclose \
	-Wl,--wrap=fgets \
	-Wl,--wrap=open \
	-Wl,--wrap=lseek \
	-Wl,--wrap=close \
	-Wl,--wrap=stat \
	-Wl,--wrap=fstat \
	-Wl,--wrap=__fxstat

#zbx_ipmi_change_callbacks

zbx_ipmi_change_callbacks_SOURCES = \
	zbx_ipmi_change_callbacks.c \
	$(COMMON_SRC_FILES)

zbx_ipmi_change_callbacks_LDADD = \
	$(IPMI_LIBS) \
	$(OPENIPMI_LIBS)

zbx_ipmi_change_callbacks_LDADD += @SERVER_LIBS@

# the test passes fabricated (never dereferenced) addresses as ipmi_sensor_t*/ipmi_control_t*, so the OpenIPMI
# calls zbx_sensor_change_cb()/zbx_control_change_cb() make on them are wrapped - see the test source
CHANGE_CALLBACKS_WRAP_FUNCS = \
	-Wl,--wrap=ipmi_sensor_convert_to_id \
	-Wl,--wrap=ipmi_control_convert_to_id \
	-Wl,--wrap=ipmi_sensor_get_is_readable

zbx_ipmi_change_callbacks_LDFLAGS = @SERVER_LDFLAGS@ $(OPENIPMI_LDFLAGS) $(MOCKDATA_WRAP_FUNCS) \
	$(CHANGE_CALLBACKS_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_ipmi_change_callbacks_CFLAGS = $(IPMI_COMPILER_FLAGS)

endif
