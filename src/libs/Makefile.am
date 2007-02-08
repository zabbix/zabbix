## Process this file with automake to produce Makefile.in

DIST_SUBDIRS = \
	zbxdbhigh \
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
	zbxsys 

if SERVER
SERVER_SUBDIRS = zbxdbhigh zbxemail zbxsms zbxcomms
endif

SUBDIRS = zbxcrypto zbxcommon zbxlog zbxnet zbxnix zbxconf zbxsysinfo zbxplugin zbxsys $(SERVER_SUBDIRS)

EXTRA_DISTR = zbxwin32
