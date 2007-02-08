## Process this file with automake to produce Makefile.in

ACLOCAL_AMFLAGS = -I m4

SUBDIRS = \
	src \
	misc \
	create

EXTRA_DIST = \
	bin \
	build \
	FAQ \
	frontends \
	include \
	test \
	upgrades

## "dist-hook" run after the distribution directory is filled, but before the actual tar (or shar) file is created.
dist-hook:
	rm -rf `find $(top_distdir) -name ".svn"`
	rm -f $(top_distdir)/frontends/php/conf/zabbix.conf.php
##TODO  makdir $(top_distdir)/docs
##TODO  cp ?????/ZABBIX Manual v1.4.pdf $(top_distdir)/docs/
