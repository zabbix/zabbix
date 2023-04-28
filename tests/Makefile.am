SUBDIRS = \
	. \
	mocks \
	libs \
	zabbix_server

noinst_LIBRARIES = \
	libzbxmocktest.a \
	libzbxmockdata.a

libzbxmocktest_a_CFLAGS = \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)

libzbxmocktest_a_SOURCES = \
	zbxmocktest.c \
	zbxmocktest.h

libzbxmockdata_a_CFLAGS = \
	$(CMOCKA_CFLAGS) \
	$(YAML_CFLAGS)

libzbxmockdata_a_SOURCES = \
	zbxmockdb.c \
	zbxmockdb.h \
	zbxmockdir.c \
	zbxmockexit.c \
	zbxmockfile.c \
	zbxmockdata.c \
	zbxmockdata.h \
	zbxmockassert.c \
	zbxmockassert.h \
	zbxmockutil.c \
	zbxmockutil.h \
	zbxmocktime.c \
	zbxmockhelper.c \
	zbxmockhelper.h \
	zbxmocklog.c \
	zbxmockjson.c \
	zbxmockjson.h
