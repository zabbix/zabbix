## Process this file with automake to produce Makefile.in

DIST_SUBDIRS = \
	zbxdbhigh \
	zbxdb \
	zbxemail \
	zbxsms \
	zbxcomms \
	zbxcrypto \
	zbxcommon \
	zbxlog \
	zbxnet \
	zbxnix \
	zbxconf \
	zbxsysinfo \
	zbxplugin \
	zbxsys \
	zbxjabber

if SERVER
SERVER_SUBDIRS = zbxdb zbxdbhigh zbxemail zbxsms zbxcomms
endif

if JABBER
JABBER_SUBDIRS = zbxjabber
endif

SUBDIRS = zbxcrypto zbxcommon zbxlog zbxnet zbxnix zbxconf zbxsysinfo zbxplugin zbxsys $(JABBER_SUBDIRS) $(SERVER_SUBDIRS)

EXTRA_DISTR = zbxwin32
