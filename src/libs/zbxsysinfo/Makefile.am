if CONFIG_HOST_LINUX
SUBDIRS=linux
libzbxsysinfo_a_LIBADD = ../zbxcommon/libzbxcommon.a ../zbxcrypto/libzbxcrypto.a linux/libzbxsysinfo2.a
endif

if CONFIG_HOST_FREEBSD
SUBDIRS=freebsd
libzbxsysinfo_a_LIBADD = ../zbxcommon/libzbxcommon.a ../zbxcrypto/libzbxcrypto.a freebsd/libzbxsysinfo2.a
endif

libzbxsysinfo_a_SOURCES=common/file.c common/system.c sysinfo.c
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

