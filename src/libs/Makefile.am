## Process this file with automake to produce Makefile.in

DIST_SUBDIRS = \
	zbxdbcache \
	zbxdbhigh \
	zbxdb \
	zbxemail \
	zbxsms \
	zbxcrypto \
	zbxcommon \
	zbxcomms \
	zbxlog \
	zbxnix \
	zbxconf \
	zbxsysinfo \
	zbxplugin \
	zbxsys \
	zbxjabber \
	zbxjson

if PROXY
PROXY_SUBDIRS = \
	zbxdb \
	zbxdbcache \
	zbxdbhigh \
	zbxjson
endif

if SERVER
SERVER_SUBDIRS = \
	zbxdb \
	zbxdbcache \
	zbxdbhigh \
	zbxemail \
	zbxsms
endif

if JABBER
JABBER_SUBDIRS = zbxjabber
endif

SUBDIRS = \
	zbxcrypto \
	zbxcommon \
	zbxlog \
	zbxnix \
	zbxconf \
	zbxsysinfo \
	zbxplugin \
	zbxsys \
	zbxcomms \
	$(JABBER_SUBDIRS) \
	$(PROXY_SUBDIRS) \
	$(SERVER_SUBDIRS)

EXTRA_DIST = zbxwin32
