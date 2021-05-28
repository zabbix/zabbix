## Process this file with automake to produce Makefile.in

DIST_SUBDIRS = \
	go \
	libs \
	zabbix_agent \
	zabbix_get \
	zabbix_sender \
	zabbix_proxy \
	zabbix_server \
	zabbix_java \
	zabbix_js

if AGENT
COMMON_SUBDIRS = libs
else
if SERVER
COMMON_SUBDIRS = libs
else
if PROXY
COMMON_SUBDIRS = libs
else
if AGENT2
COMMON_SUBDIRS = libs
endif
endif
endif
endif

if AGENT
AGENT_SUBDIRS = \
	zabbix_agent \
	zabbix_get \
	zabbix_sender
endif

if AGENT2
if !AGENT 
AGENT_SUBDIRS = zabbix_agent
endif
GO_SUBDIRS = \
	go
endif

if WEBSERVICE
GO_SUBDIRS = \
	go
endif

if SERVER
SERVER_SUBDIRS = \
	zabbix_server \
	zabbix_js
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
	zabbix_server/selfmon \
	zabbix_server/snmptrapper \
	zabbix_server/vmware \
	zabbix_server/ipmi \
	zabbix_server/odbc \
	zabbix_server/scripts \
	zabbix_server/preprocessor \
	zabbix_server/availability \
	zabbix_server/service \
	zabbix_proxy \
	zabbix_js
	
endif
endif

if JAVA
JAVA_SUBDIRS = \
	zabbix_java
endif

SUBDIRS = \
	$(COMMON_SUBDIRS) \
	$(AGENT_SUBDIRS) \
	$(GO_SUBDIRS) \
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
