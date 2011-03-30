## Process this file with automake to produce Makefile.in

DIST_SUBDIRS = \
	zbxdbcache \
	zbxdbhigh \
	zbxdb \
	zbxmemory \
	zbxalgo \
	zbxemail \
	zbxsms \
	zbxcrypto \
	zbxcommon \
	zbxcomms \
	zbxcommshigh \
	zbxlog \
	zbxnix \
	zbxconf \
	zbxsysinfo \
	zbxplugin \
	zbxsys \
	zbxjabber \
	zbxjson \
	zbxserver \
	zbxicmpping

if PROXY
PROXY_SUBDIRS = \
	zbxcommshigh \
	zbxdb \
	zbxdbcache \
	zbxdbhigh \
	zbxmemory \
	zbxserver \
	zbxicmpping
endif

if SERVER
SERVER_SUBDIRS = \
	zbxcommshigh \
	zbxdb \
	zbxdbcache \
	zbxdbhigh \
	zbxmemory \
	zbxemail \
	zbxsms \
	zbxserver \
	zbxicmpping
endif

if JABBER
JABBER_SUBDIRS = zbxjabber
endif

SUBDIRS = \
	zbxcrypto \
	zbxcommon \
	zbxlog \
	zbxalgo \
	zbxnix \
	zbxconf \
	zbxsysinfo \
	zbxplugin \
	zbxsys \
	zbxcomms \
	zbxjson \
	$(JABBER_SUBDIRS) \
	$(PROXY_SUBDIRS) \
	$(SERVER_SUBDIRS)

EXTRA_DIST = zbxwin32
