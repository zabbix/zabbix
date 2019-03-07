## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxpoller.a libzbxpoller_server.a libzbxpoller_proxy.a

libzbxpoller_a_SOURCES = \
	checks_agent.c checks_agent.h \
	checks_internal.c checks_internal.h \
	checks_simple.c checks_simple.h \
	checks_simple_vmware.c checks_simple_vmware.h \
	checks_snmp.c checks_snmp.h \
	checks_db.c checks_db.h \
	checks_aggregate.c checks_aggregate.h \
	checks_external.c checks_external.h \
	checks_ssh.c checks_ssh.h \
	checks_telnet.c checks_telnet.h \
	checks_java.c checks_java.h \
	checks_calculated.c checks_calculated.h \
	checks_http.c checks_http.h \
	poller.c poller.h
	
libzbxpoller_server_a_SOURCES = \
	checks_internal_server.c checks_internal.h

libzbxpoller_proxy_a_SOURCES = \
	checks_internal_proxy.c checks_internal.h

libzbxpoller_a_CFLAGS = -I@top_srcdir@/src/libs/zbxsysinfo/simple -I@top_srcdir@/src/libs/zbxdbcache @SNMP_CFLAGS@ @SSH2_CFLAGS@

libzbxpoller_server_a_CFLAGS = -I@top_srcdir@/src/libs/zbxdbcache
