SUBDIRS=
lib_LIBRARIES=libzbxsysinfo.a
if CONFIG_HOST_LINUX
	libzbxsysinfo_a_SOURCES=linux/cpu.c linux/diskio.c linux/diskspace.c linux/file.c linux/inodes.c  linux/memory.c linux/sensors.c  linux/swap.c  linux/uptime.c sysinfo.c linux/system.c
else
	libzbxsysinfo_a_SOURCES=@ZBX_OS@/cpu.c @ZBX_OS@/diskio.c @ZBX_OS@/diskspace.c @ZBX_OS@/file.c @ZBX_OS@/inodes.c  @ZBX_OS@/memory.c @ZBX_OS@/sensors.c  @ZBX_OS@/swap.c  @ZBX_OS@/uptime.c sysinfo.c @ZBX_OS@/system.c
endif
libzbxsysinfo_a_LIBADD = ../zbxcrypto/libzbxcrypto.a
