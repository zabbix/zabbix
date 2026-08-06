# Redirect for Makefile.am files under tests/zabbix_server/*
# (e.g. tests/zabbix_server/lld, pinger, service, trapper).
#
# Automake resolves the "include ../grouped-libs.mk.inc" statement inside
# ../../libs/Makefile.include relative to the including Makefile.am, not
# relative to Makefile.include's own directory. For a caller under
# tests/zabbix_server/* that resolves to this file's location, so this file
# exists purely to bounce the include to the real tests/libs/grouped-libs.mk.inc.
include ../../libs/grouped-libs.mk.inc
