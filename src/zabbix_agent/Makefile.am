SUBDIRS =
bin_PROGRAMS = zabbix_agent zabbix_agentd
zabbix_agent_SOURCES =  zabbix_agent.c zbxconf.c alias.c
zabbix_agent_LDFLAGS = @LDAP_LFLAGS@
zabbix_agent_LDADD = ../libs/zbxsysinfo/libzbxsysinfo.a ../libs/zbxsysinfo/@ARCH@/libzbxsysinfo2.a \
../libs/zbxlog/libzbxlog.a ../libs/zbxsys/libzbxsys.a ../libs/zbxnix/libzbxnix.a ../libs/zbxnet/libzbxnet.a \
../libs/zbxconf/libzbxconf.a ../libs/zbxcommon/libzbxcommon.a ../libs/zbxcrypto/libzbxcrypto.a @LDAP_LIBS@
zabbix_agentd_SOURCES = active.c  cpustat.c  diskdevices.c  interfaces.c  logfiles.c  stats.c  zabbix_agentd.c zbxconf.c alias.c listener.c
zabbix_agentd_LDFLAGS = @LDAP_LFLAGS@ -DZABBIX_DAEMON
zabbix_agentd_LDADD = ../libs/zbxsysinfo/libzbxsysinfo.a ../libs/zbxsysinfo/@ARCH@/libzbxsysinfo2.a ../libs/zbxlog/libzbxlog.a \
../libs/zbxnix/libzbxnix.a ../libs/zbxsys/libzbxsys.a ../libs/zbxnet/libzbxnet.a ../libs/zbxconf/libzbxconf.a \
../libs/zbxcommon/libzbxcommon.a ../libs/zbxcrypto/libzbxcrypto.a @LDAP_LIBS@
