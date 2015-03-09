#!/bin/sh

# Run this script to generate 'configure'

aclocal -I m4
autoconf
autoheader
automake -a
automake
