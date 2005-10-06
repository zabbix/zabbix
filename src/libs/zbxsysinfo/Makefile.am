SUBDIRS=

if CONFIG_HOST_HPUX
libzbxsysinfo_a_SOURCES=linux/cpu.c linux/diskio.c linux/diskspace.c linux/file.c linux/inodes.c linux/memory.c linux/sensors.c  linux/swap.c  linux/uptime.c sysinfo.c linux/system.c linux/param.c 
endif

if CONFIG_HOST_LINUX
libzbxsysinfo_a_SOURCES=linux/cpu.c linux/diskio.c linux/diskspace.c linux/file.c linux/inodes.c linux/memory.c linux/sensors.c  linux/swap.c  linux/uptime.c sysinfo.c linux/system.c linux/param.c 
endif

if CONFIG_HOST_SOLARIS
libzbxsysinfo_a_SOURCES=linux/cpu.c linux/diskio.c linux/diskspace.c linux/file.c linux/inodes.c linux/memory.c linux/sensors.c  linux/swap.c  linux/uptime.c sysinfo.c linux/system.c linux/param.c 
endif

if CONFIG_HOST_UNKNOWN
libzbxsysinfo_a_SOURCES=linux/cpu.c linux/diskio.c linux/diskspace.c linux/file.c linux/inodes.c linux/memory.c linux/sensors.c  linux/swap.c  linux/uptime.c sysinfo.c linux/system.c linux/param.c 
endif

lib_LIBRARIES=libzbxsysinfo.a
libzbxsysinfo_a_LIBADD = ../zbxcommon/libzbxcommon.a ../zbxcrypto/libzbxcrypto.a
