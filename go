#!/bin/sh

mkdir bin
aclocal
autoconf
autoheader
automake
./configure --with-mysql
make
