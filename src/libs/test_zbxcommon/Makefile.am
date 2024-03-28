# This bin exists to ensure that libzbxcommon does not depend on any other Zabbix library.
# It cannot be done in cmocka, since all compilation objects in tests directory are wrapped with undefined
# references, which can be resolved by linking various others Zabbix libraries like libzbxnix.a
# Unfortunately, libzbxcommon depends currently on other libraries. This needs to be fixed.
# Once this is done, the test_libzbxcommon_bin rules can be moved to zbxcommon directory.
bin_PROGRAMS = test_libzbxcommon_bin

test_libzbxcommon_bin_SOURCES = test_libzbxcommon_bin.c

test_libzbxcommon_bin_LDADD =  \
        $(top_builddir)/src/libs/zbxcommon/libzbxcommon.a \
        $(top_builddir)/src/libs/zbxnix/libzbxnix.a \
        $(top_builddir)/src/libs/zbxlog/libzbxlog.a \
        $(top_builddir)/src/libs/zbxstr/libzbxstr.a \
        $(top_builddir)/src/libs/zbxcommon/libzbxcommon.a
