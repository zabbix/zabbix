SUBDIRS =
bin_PROGRAMS = zabbix_agent zabbix_agentd
zabbix_agent_SOURCES =  zabbix_agent.c
zabbix_agent_LDADD = ../libs/zbxsysinfo/libzbxsysinfo.a ../libs/zbxlog/libzbxlog.a \
../libs/zbxcrypto/libzbxcrypto.a ../libs/zbxnet/libzbxnet.a ../libs/zbxconf/libzbxconf.a  ../libs/zbxcommon/libzbxcommon.a \
@LDAP_LFLAGS@
zabbix_agentd_SOURCES = active.c  cpustat.c  diskdevices.c  interfaces.c  logfiles.c  stats.c  zabbix_agentd.c
zabbix_agentd_LDADD = ../libs/zbxsysinfo/libzbxsysinfo.a ../libs/zbxlog/libzbxlog.a ../libs/zbxcrypto/libzbxcrypto.a \
../libs/zbxpid/libzbxpid.a ../libs/zbxnet/libzbxnet.a ../libs/zbxconf/libzbxconf.a \
../libs/zbxcommon/libzbxcommon.a @LDAP_LFLAGS@
