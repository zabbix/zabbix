#!/bin/sh

mkdir bin
aclocal
autoconf
autoheader
automake -a
automake
./configure --with-mysql
make
