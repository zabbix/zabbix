#!/bin/sh

# Run this script to generate 'configure'

if [ "xtests" = "x$1" ]; then
	cp -f ./tests/conf_tests.m4 ./m4/ || exit $?
fi

aclocal -I m4
autoconf
autoheader
automake -a
automake
rm -f ./m4/conf_tests.m4
