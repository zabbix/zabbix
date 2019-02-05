#!/bin/sh

# Run this script to generate 'configure'

usage()
{
	echo "usage: $0 [tests]"
	exit 1
}

[ $# -gt 1 ] && usage

if [ $# -eq 1 ]; then
	[ $1 = "tests" ] || usage

	cp -f tests/conf_tests.m4 m4/ || exit $?
else
	[ -f m4/conf_tests.m4 ] && rm -f m4/conf_tests.m4
fi

aclocal -I m4
autoconf
autoheader
automake -a
automake

if [ "x$1" != "xtests" ] && [ -f m4/conf_tests.m4 ]; then
	rm -f m4/conf_tests.m4
fi
