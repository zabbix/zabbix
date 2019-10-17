#!/bin/sh

# Run this script to generate 'configure'

usage()
{
	echo "usage: $0 [tests]"
	exit 1
}

case $# in
	0)
		# disable tests
		rm -f m4/conf_tests.m4
		;;
	1)
		[ $1 = "tests" ] || usage

		# enable tests
		cp -f tests/conf_tests.m4 m4/ || exit $?
		;;
	*)
		usage
		;;
esac

aclocal -I m4
autoconf
autoheader
automake -a
automake
