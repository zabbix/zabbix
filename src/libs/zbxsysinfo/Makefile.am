if CONFIG_HOST_LINUX
SUBDIRS=linux
libzbxsysinfo_a_LIBADD =  linux/libzbxsysinfo2.a ../zbxcommon/libzbxcommon.a ../zbxcrypto/libzbxcrypto.a
endif

if CONFIG_HOST_FREEBSD
SUBDIRS=freebsd
libzbxsysinfo_a_LIBADD = freebsd/libzbxsysinfo2.a ../zbxcommon/libzbxcommon.a ../zbxcrypto/libzbxcrypto.a
endif

libzbxsysinfo2_a_SOURCES=common/common.c common/file.c common/system.c
lib_LIBRARIES=libzbxsysinfo.a

if CONFIG_HOST_HPUX
endif

if CONFIG_HOST_FREEBSD
endif

if CONFIG_HOST_LINUX
endif

if CONFIG_HOST_OPENBSD
endif

if CONFIG_HOST_SOLARIS
endif

if CONFIG_HOST_UNKNOWN
endif

