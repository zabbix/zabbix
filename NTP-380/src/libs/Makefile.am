## Process this file with automake to produce Makefile.in

DIST_SUBDIRS = \
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
	zbxdbcache \
	zbxexec

if SERVER
SERVER_SUBDIRS = \
	zbxdb \
	zbxdbhigh \
	zbxemail \
	zbxsms \
	zbxdbcache
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
	$(SERVER_SUBDIRS)

EXTRA_DIST = zbxwin32
