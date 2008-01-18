## Process this file with automake to produce Makefile.in

DIST_SUBDIRS = \
	libs \
	zabbix_agent \
	zabbix_get \
	zabbix_sender \
	zabbix_proxy \
	zabbix_server

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
PROXY_SUBDIRS = zabbix_proxy
endif

SUBDIRS = \
	libs \
	$(AGENT_SUBDIRS) \
	$(PROXY_SUBDIRS) \
	$(SERVER_SUBDIRS)
