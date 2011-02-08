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
	zbxjson \
	zbxserver \
	zbxicmpping \
	zbxexec

if PROXY
PROXY_SUBDIRS = \
	zbxdb \
	zbxdbcache \
	zbxdbhigh \
	zbxserver \
	zbxicmpping
endif

if SERVER
SERVER_SUBDIRS = \
	zbxdb \
	zbxdbcache \
	zbxdbhigh \
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
	zbxnix \
	zbxconf \
	zbxsysinfo \
	zbxplugin \
	zbxsys \
	zbxcomms \
	zbxjson \
	zbxexec \
	$(JABBER_SUBDIRS) \
	$(PROXY_SUBDIRS) \
	$(SERVER_SUBDIRS)

EXTRA_DIST = zbxwin32
