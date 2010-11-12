## Process this file with automake to produce Makefile.in

ACLOCAL_AMFLAGS = -I m4

SUBDIRS = \
	create \
	misc \
	src \
	upgrades

EXTRA_DIST = \
	bin \
	build \
	docs \
	FAQ \
	frontends \
	include \
	test

## "dist-hook" run after the distribution directory is filled, but before the actual tar (or shar) file is created.
dist-hook:
	rm -rf `find $(top_distdir) -name ".svn"`
	rm -f $(top_distdir)/frontends/php/conf/zabbix.conf.php
	rm -f $(top_distdir)/do
	rm -f $(top_distdir)/go
	rm -f $(top_distdir)/splint
	rm -rf $(top_distdir)/test
	rm -f $(top_distdir)/TODO
##TODO  makdir $(top_distdir)/docs
##TODO  cp ?????/ZABBIX Manual v1.4.pdf $(top_distdir)/docs/
