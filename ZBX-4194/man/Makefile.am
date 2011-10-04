## Process this file with automake to produce Makefile.in

if AGENT
agent_MANS = zabbix_agentd.man
client_MANS = zabbix_get.man zabbix_sender.man
endif

if PROXY
proxy_MANS = zabbix_proxy.man
endif

if SERVER
server_MANS = zabbix_server.man
endif

man1_MANS = $(client_MANS)
man8_MANS = $(agent_MANS) $(proxy_MANS) $(server_MANS)

EXTRA_DIST = \
	zabbix_agentd.man \
	zabbix_get.man \
	zabbix_sender.man \
	zabbix_proxy.man \
	zabbix_server.man
