# Redirect for Makefile.am files one directory deeper than tests/libs/*
# (e.g. tests/libs/zbxsysinfo/agent, tests/libs/zbxsysinfo/linux).
#
# Automake resolves the "include ../grouped-libs.mk.inc" statement inside
# ../Makefile.include relative to the including Makefile.am, not relative to
# Makefile.include's own directory. For a caller two levels below tests/libs
# that resolves to this file's location, so this file exists purely to bounce
# the include to the real tests/libs/grouped-libs.mk.inc.
include ../../grouped-libs.mk.inc
