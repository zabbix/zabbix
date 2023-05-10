noinst_LIBRARIES = libvaluecachemock.a

libvaluecachemock_a_SOURCES = \
	valuecache_mock.c \
	valuecache_mock.h

libvaluecachemock_a_CFLAGS = \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/src/libs/zbxhistory \
	-I@top_srcdir@/tests \
	$(TLS_CFLAGS) $(CMOCKA_CFLAGS) $(YAML_CFLAGS)
