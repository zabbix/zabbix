noinst_LIBRARIES = libconfigcachemock.a

libconfigcachemock_a_SOURCES = \
	copnfigcache_mock.h \
	configcache.c configcache.h \
	usermacros.c \
	hosts.c

libconfigcachemock_a_CFLAGS = \
	-I@top_srcdir@/src/libs/zbxalgo \
	-I@top_srcdir@/src/libs/zbxcacheconfig \
	-I@top_srcdir@/src/libs/zbxcachehistory \
	-I@top_srcdir@/src/libs/zbxcachevalue \
	-I@top_srcdir@/tests \
	$(TLS_CFLAGS) $(CMOCKA_CFLAGS) $(YAML_CFLAGS)
