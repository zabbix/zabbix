include ../Makefile.include

if HAVE_IPMI

BINARIES_tests = \
	zbx_get_ipmi_host \
	zbx_domain_closed_cb \
	zbx_read_ipmi_sensor \
	zbx_read_ipmi_control \
	zbx_ipmi_pure_helpers \
	zbx_ipmi_sensor_lookup \
	zbx_ipmi_control_lookup \
	zbx_close_inactive_host \
	zbx_read_ipmi_thresholds \
	zbx_ipmi_control_callbacks \
	zbx_ipmi_sensor_callbacks \
	zbx_ipmi_change_callbacks \
	zbx_vlog \
	zbx_ipmi_uninitialized_entry

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

# for tests that stand in a fabricated (never-dereferenced) address for a real ipmi_sensor_t*/ipmi_control_t*:
# ipmi_sensor_convert_to_id()/ipmi_control_convert_to_id() are the only OpenIPMI calls in checks_ipmi.c that
# would dereference such a pointer, so they are wrapped to echo it back into the ID unchanged instead
SENSOR_CONVERT_WRAP_FUNCS = \
	-Wl,--wrap=ipmi_sensor_convert_to_id

CONTROL_CONVERT_WRAP_FUNCS = \
	-Wl,--wrap=ipmi_control_convert_to_id

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

#zbx_read_ipmi_sensor

zbx_read_ipmi_sensor_SOURCES = \
	zbx_read_ipmi_sensor.c \
	$(COMMON_SRC_FILES)

zbx_read_ipmi_sensor_LDADD = \
	$(IPMI_LIBS) \
	$(OPENIPMI_LIBS)

zbx_read_ipmi_sensor_LDADD += @SERVER_LIBS@

zbx_read_ipmi_sensor_LDFLAGS = @SERVER_LDFLAGS@ $(OPENIPMI_LDFLAGS) $(MOCKDATA_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_read_ipmi_sensor_CFLAGS = $(IPMI_COMPILER_FLAGS)

#zbx_read_ipmi_control

zbx_read_ipmi_control_SOURCES = \
	zbx_read_ipmi_control.c \
	$(COMMON_SRC_FILES)

zbx_read_ipmi_control_LDADD = \
	$(IPMI_LIBS) \
	$(OPENIPMI_LIBS)

zbx_read_ipmi_control_LDADD += @SERVER_LIBS@

zbx_read_ipmi_control_LDFLAGS = @SERVER_LDFLAGS@ $(OPENIPMI_LDFLAGS) $(MOCKDATA_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_read_ipmi_control_CFLAGS = $(IPMI_COMPILER_FLAGS)

#zbx_ipmi_pure_helpers

zbx_ipmi_pure_helpers_SOURCES = \
	zbx_ipmi_pure_helpers.c \
	$(COMMON_SRC_FILES)

zbx_ipmi_pure_helpers_LDADD = \
	$(IPMI_LIBS) \
	$(OPENIPMI_LIBS)

zbx_ipmi_pure_helpers_LDADD += @SERVER_LIBS@

zbx_ipmi_pure_helpers_LDFLAGS = @SERVER_LDFLAGS@ $(OPENIPMI_LDFLAGS) $(MOCKDATA_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_ipmi_pure_helpers_CFLAGS = $(IPMI_COMPILER_FLAGS)

#zbx_ipmi_sensor_lookup

zbx_ipmi_sensor_lookup_SOURCES = \
	zbx_ipmi_sensor_lookup.c \
	$(COMMON_SRC_FILES)

zbx_ipmi_sensor_lookup_LDADD = \
	$(IPMI_LIBS) \
	$(OPENIPMI_LIBS)

zbx_ipmi_sensor_lookup_LDADD += @SERVER_LIBS@

zbx_ipmi_sensor_lookup_LDFLAGS = @SERVER_LDFLAGS@ $(OPENIPMI_LDFLAGS) $(MOCKDATA_WRAP_FUNCS) \
	$(SENSOR_CONVERT_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_ipmi_sensor_lookup_CFLAGS = $(IPMI_COMPILER_FLAGS)

#zbx_ipmi_control_lookup

zbx_ipmi_control_lookup_SOURCES = \
	zbx_ipmi_control_lookup.c \
	$(COMMON_SRC_FILES)

zbx_ipmi_control_lookup_LDADD = \
	$(IPMI_LIBS) \
	$(OPENIPMI_LIBS)

zbx_ipmi_control_lookup_LDADD += @SERVER_LIBS@

zbx_ipmi_control_lookup_LDFLAGS = @SERVER_LDFLAGS@ $(OPENIPMI_LDFLAGS) $(MOCKDATA_WRAP_FUNCS) \
	$(CONTROL_CONVERT_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_ipmi_control_lookup_CFLAGS = $(IPMI_COMPILER_FLAGS)

#zbx_close_inactive_host

zbx_close_inactive_host_SOURCES = \
	zbx_close_inactive_host.c \
	$(COMMON_SRC_FILES)

zbx_close_inactive_host_LDADD = \
	$(IPMI_LIBS) \
	$(OPENIPMI_LIBS)

zbx_close_inactive_host_LDADD += @SERVER_LIBS@

# exercises the "domain found by name" path (ipmi_domain_iterate_domains -> ipmi_domain_pointer_cb ->
# ipmi_domain_close), which needs a live OpenIPMI domain above and so is wrapped here instead
CLOSE_INACTIVE_HOST_WRAP_FUNCS = \
	-Wl,--wrap=ipmi_domain_iterate_domains \
	-Wl,--wrap=ipmi_domain_get_name \
	-Wl,--wrap=ipmi_domain_convert_to_id \
	-Wl,--wrap=ipmi_domain_pointer_cb \
	-Wl,--wrap=ipmi_domain_close

zbx_close_inactive_host_LDFLAGS = @SERVER_LDFLAGS@ $(OPENIPMI_LDFLAGS) $(MOCKDATA_WRAP_FUNCS) \
	$(CLOSE_INACTIVE_HOST_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_close_inactive_host_CFLAGS = $(IPMI_COMPILER_FLAGS)

#zbx_read_ipmi_thresholds

zbx_read_ipmi_thresholds_SOURCES = \
	zbx_read_ipmi_thresholds.c \
	$(COMMON_SRC_FILES)

zbx_read_ipmi_thresholds_LDADD = \
	$(IPMI_LIBS) \
	$(OPENIPMI_LIBS)

zbx_read_ipmi_thresholds_LDADD += @SERVER_LIBS@

# wraps ipmi_sensor_id_get_thresholds() purely to count calls (forwarding to the real implementation via
# __real_) so the two "not accessible"/"fixed" cases can be told apart from the "readable" one - see the
# file header comment
zbx_read_ipmi_thresholds_LDFLAGS = @SERVER_LDFLAGS@ $(OPENIPMI_LDFLAGS) $(MOCKDATA_WRAP_FUNCS) \
	-Wl,--wrap=ipmi_sensor_id_get_thresholds $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_read_ipmi_thresholds_CFLAGS = $(IPMI_COMPILER_FLAGS)

#zbx_ipmi_control_callbacks

zbx_ipmi_control_callbacks_SOURCES = \
	zbx_ipmi_control_callbacks.c \
	$(COMMON_SRC_FILES)

zbx_ipmi_control_callbacks_LDADD = \
	$(IPMI_LIBS) \
	$(OPENIPMI_LIBS)

zbx_ipmi_control_callbacks_LDADD += @SERVER_LIBS@

zbx_ipmi_control_callbacks_LDFLAGS = @SERVER_LDFLAGS@ $(OPENIPMI_LDFLAGS) $(MOCKDATA_WRAP_FUNCS) \
	$(CONTROL_CONVERT_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_ipmi_control_callbacks_CFLAGS = $(IPMI_COMPILER_FLAGS)

#zbx_ipmi_sensor_callbacks

zbx_ipmi_sensor_callbacks_SOURCES = \
	zbx_ipmi_sensor_callbacks.c \
	$(COMMON_SRC_FILES)

zbx_ipmi_sensor_callbacks_LDADD = \
	$(IPMI_LIBS) \
	$(OPENIPMI_LIBS)

zbx_ipmi_sensor_callbacks_LDADD += @SERVER_LIBS@

zbx_ipmi_sensor_callbacks_LDFLAGS = @SERVER_LDFLAGS@ $(OPENIPMI_LDFLAGS) $(MOCKDATA_WRAP_FUNCS) \
	$(SENSOR_CONVERT_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_ipmi_sensor_callbacks_CFLAGS = $(IPMI_COMPILER_FLAGS)

#zbx_ipmi_change_callbacks

zbx_ipmi_change_callbacks_SOURCES = \
	zbx_ipmi_change_callbacks.c \
	$(COMMON_SRC_FILES)

zbx_ipmi_change_callbacks_LDADD = \
	$(IPMI_LIBS) \
	$(OPENIPMI_LIBS)

zbx_ipmi_change_callbacks_LDADD += @SERVER_LIBS@

# exercises zbx_sensor_change_cb()/zbx_connection_change_cb() too, which need a few more real OpenIPMI
# calls wrapped beyond the sensor/control ID conversion above - see the file header comment
CHANGE_CALLBACKS_EXTRA_WRAP_FUNCS = \
	-Wl,--wrap=ipmi_sensor_convert_to_id \
	-Wl,--wrap=ipmi_sensor_get_is_readable \
	-Wl,--wrap=ipmi_domain_close \
	-Wl,--wrap=ipmi_domain_add_entity_update_handler

zbx_ipmi_change_callbacks_LDFLAGS = @SERVER_LDFLAGS@ $(OPENIPMI_LDFLAGS) $(MOCKDATA_WRAP_FUNCS) \
	$(CONTROL_CONVERT_WRAP_FUNCS) $(CHANGE_CALLBACKS_EXTRA_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_ipmi_change_callbacks_CFLAGS = $(IPMI_COMPILER_FLAGS)

#zbx_vlog

zbx_vlog_SOURCES = \
	zbx_vlog.c \
	$(COMMON_SRC_FILES)

zbx_vlog_LDADD = \
	$(IPMI_LIBS) \
	$(OPENIPMI_LIBS)

zbx_vlog_LDADD += @SERVER_LIBS@

zbx_vlog_LDFLAGS = @SERVER_LDFLAGS@ $(OPENIPMI_LDFLAGS) $(MOCKDATA_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_vlog_CFLAGS = $(IPMI_COMPILER_FLAGS)

#zbx_ipmi_uninitialized_entry

zbx_ipmi_uninitialized_entry_SOURCES = \
	zbx_ipmi_uninitialized_entry.c \
	$(COMMON_SRC_FILES)

zbx_ipmi_uninitialized_entry_LDADD = \
	$(IPMI_LIBS) \
	$(OPENIPMI_LIBS)

zbx_ipmi_uninitialized_entry_LDADD += @SERVER_LIBS@

zbx_ipmi_uninitialized_entry_LDFLAGS = @SERVER_LDFLAGS@ $(OPENIPMI_LDFLAGS) $(MOCKDATA_WRAP_FUNCS) $(CMOCKA_LDFLAGS) $(YAML_LDFLAGS)

zbx_ipmi_uninitialized_entry_CFLAGS = $(IPMI_COMPILER_FLAGS)

endif
