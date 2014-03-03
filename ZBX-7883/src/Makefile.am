## Process this file with automake to produce Makefile.in

DIST_SUBDIRS = \
	libs \
	zabbix_agent \
	zabbix_get \
	zabbix_sender \
	zabbix_proxy \
	zabbix_server \
	zabbix_java

if AGENT
AGENT_SUBDIRS = \
	zabbix_agent \
	zabbix_get \
	zabbix_sender
endif

if SERVER
SERVER_SUBDIRS = zabbix_server
endif

if PROXY
if SERVER
PROXY_SUBDIRS = \
	zabbix_proxy
else
PROXY_SUBDIRS = \
	zabbix_server/dbsyncer \
	zabbix_server/dbconfig \
	zabbix_server/discoverer \
	zabbix_server/httppoller \
	zabbix_server/pinger \
	zabbix_server/poller \
	zabbix_server/trapper \
	zabbix_server/nodewatcher \
	zabbix_server/selfmon \
	zabbix_server/snmptrapper \
	zabbix_server/vmware \
	zabbix_proxy
endif
endif

if JAVA
JAVA_SUBDIRS = \
	zabbix_java
endif

SUBDIRS = \
	libs \
	$(AGENT_SUBDIRS) \
	$(SERVER_SUBDIRS) \
	$(PROXY_SUBDIRS) \
	$(JAVA_SUBDIRS)

## "dist-hook" run after the distribution directory is filled, but before the actual tar (or shar) file is created.
dist-hook:
	mkdir $(distdir)/modules
	mkdir $(distdir)/modules/dummy
	cp $(srcdir)/modules/dummy/*.c $(distdir)/modules/dummy/
	cp $(srcdir)/modules/dummy/Makefile $(distdir)/modules/dummy/
	cp $(srcdir)/modules/dummy/README $(distdir)/modules/dummy/
