## Process this file with automake to produce Makefile.in

AGENT_DIRS = zabbix_agent zabbix_get zabbix_sender
SERVER_DIRS = zabbix_server

if AGENT
AGENT_SUBDIRS = $(AGENT_DIRS)
else
AGENT_EXTRA_SUBDIRS = $(AGENT_DIRS)
endif

if SERVER
SERVER_SUBDIRS = $(SERVER_DIRS)
else
SERVER_EXTRA_SUBDIRS = $(SERVER_DIRS)
endif

SUBDIRS = libs $(AGENT_SUBDIRS) $(SERVER_SUBDIRS)
EXTRA_SUBDIRS = $(AGENT_EXTRA_SUBDIRS) $(SERVER_EXTRA_SUBDIRS)
