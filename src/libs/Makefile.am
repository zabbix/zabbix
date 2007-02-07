## Process this file with automake to produce Makefile.in

SERVER_DIRS = zbxdbhigh zbxemail zbxsms zbxcomms

if SERVER
SERVER_SUBDIRS = $(SERVER_DIRS)
else
EXTRA_SUBDIRS = $(SERVER_DIRS)
endif

SUBDIRS = zbxcrypto zbxcommon zbxlog zbxnet zbxnix zbxconf zbxsysinfo zbxplugin zbxsys $(SERVER_SUBDIRS)

