# This binary exists to ensure that libzbxcommon does not depend on any other Zabbix library.
# There is already zbxcommon libs folder, but there are MOCK_DATA_DEPS MOCK_TEST_DEPS dependencies on cmocka.
# However, to make it very clear and obvious that common should not have any dependencies at all,
# it is preferred to keep this test in a separate dedicate directory and be a separate build target.
bin_PROGRAMS = test_libzbxcommon_bin

test_libzbxcommon_bin_SOURCES = test_libzbxcommon_bin.c

test_libzbxcommon_bin_LDADD =  \
        $(top_builddir)/src/libs/zbxcommon/libzbxcommon.a
