SUBDIRS=

if CONFIG_HOST_HPUX
libzbxsysinfo_a_SOURCES=common/file.c common/system.c linux/supported.c linux/cpu.c linux/diskio.c linux/diskspace.c linux/inodes.c linux/memory.c linux/sensors.c  linux/swap.c  linux/uptime.c sysinfo.c
endif

if CONFIG_HOST_FREEBSD
libzbxsysinfo_a_SOURCES=common/file.c common/system.c freebsd/supported.c freebsd/cpu.c freebsd/diskio.c freebsd/diskspace.c freebsd/inodes.c freebsd/memory.c freebsd/sensors.c  freebsd/swap.c  freebsd/uptime.c sysinfo.c
endif

if CONFIG_HOST_LINUX
libzbxsysinfo_a_SOURCES=common/file.c common/system.c linux/supported.c linux/cpu.c linux/diskio.c linux/diskspace.c linux/inodes.c linux/memory.c linux/sensors.c  linux/swap.c  linux/uptime.c sysinfo.c
endif

if CONFIG_HOST_OPENBSD
libzbxsysinfo_a_SOURCES=common/file.c common/system.c linux/supported.c linux/cpu.c linux/diskio.c linux/diskspace.c linux/inodes.c linux/memory.c linux/sensors.c  linux/swap.c  linux/uptime.c sysinfo.c
endif

if CONFIG_HOST_SOLARIS
libzbxsysinfo_a_SOURCES=common/file.c common/system.c linux/supported.c linux/cpu.c linux/diskio.c linux/diskspace.c linux/inodes.c linux/memory.c linux/sensors.c  linux/swap.c  linux/uptime.c sysinfo.c
endif

if CONFIG_HOST_UNKNOWN
libzbxsysinfo_a_SOURCES=common/file.c common/system.c linux/supported.c linux/cpu.c linux/diskio.c linux/diskspace.c linux/inodes.c linux/memory.c linux/sensors.c  linux/swap.c  linux/uptime.c sysinfo.c
endif

lib_LIBRARIES=libzbxsysinfo.a
libzbxsysinfo_a_LIBADD = ../zbxcommon/libzbxcommon.a ../zbxcrypto/libzbxcrypto.a
