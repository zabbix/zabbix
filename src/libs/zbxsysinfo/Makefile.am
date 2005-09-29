SUBDIRS=

if CONFIG_HOST_LINUX
        libzbxsysinfo_a_SOURCES=linux/cpu.c linux/diskio.c linux/diskspace.c linux/file.c linux/inodes.c  linux/memory.c linux
/sensors.c  linux/swap.c  linux/uptime.c sysinfo.c linux/system.c linux/regexp.c linux/param.c
endif

if CONFIG_HOST_SOLARIS
        libzbxsysinfo_a_SOURCES=solaris/cpu.c solaris/diskio.c solaris/diskspace.c solaris/file.c solaris/inodes.c  solaris/me
mory.c solaris/sensors.c  solaris/swap.c  solaris/uptime.c sysinfo.c solaris/system.c
endif

lib_LIBRARIES=libzbxsysinfo.a
libzbxsysinfo_a_LIBADD = ../zbxcrypto/libzbxcrypto.a
