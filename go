#!/bin/sh

mkdir bin
autoheader
autoconf
./configure --with-mysql
make
