include ../Makefile.include

if HAVE_IPMI

BINARIES_tests = \
	zbx_get_ipmi_host \
	zbx_domain_closed_cb

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

#zbx_get_ipmi_host

zbx_get_ipmi_host_SOURCES = \
	zbx_get_ipmi_host.c \
	$(COMMON_SRC_FILES)

zbx_get_ipmi_host_LDADD = \
	$(IPMI_LIBS) \
	$(OPENIPMI_LIBS)

zbx_get_ipmi_host_LDADD += @SERVER_LIBS@

zbx_get_ipmi_host_LDFLAGS = @SERVER_LDFLAGS@ $(OPENIPMI_LDFLAGS) $(MOCKDATA_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_get_ipmi_host_CFLAGS = $(IPMI_COMPILER_FLAGS)

#zbx_domain_closed_cb

zbx_domain_closed_cb_SOURCES = \
	zbx_domain_closed_cb.c \
	$(COMMON_SRC_FILES)

zbx_domain_closed_cb_LDADD = \
	$(IPMI_LIBS) \
	$(OPENIPMI_LIBS)

zbx_domain_closed_cb_LDADD += @SERVER_LIBS@

zbx_domain_closed_cb_LDFLAGS = @SERVER_LDFLAGS@ $(OPENIPMI_LDFLAGS) $(MOCKDATA_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_domain_closed_cb_CFLAGS = $(IPMI_COMPILER_FLAGS)

endif
