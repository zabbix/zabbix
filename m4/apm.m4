# Copyright (C) 2001-2026 Zabbix SIA
#
# This program is free software: you can redistribute it and/or modify it under the terms of
# the GNU Affero General Public License as published by the Free Software Foundation, version 3.
#
# This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
# without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# See the GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License along with this program.
# If not, see <https://www.gnu.org/licenses/>.
#
# ENUM_CHECK(ENUM,INCLUDE)
#
#   Checks if the specified enumerator (or macro) constant exists
#   in a header and defines C macro with prefix HAVE_.
#

dnl a macro to check for OTLP/gRPC event collector (APM) support in the proxy
dnl requires pkg-config, grpc++ >= 1.40, protobuf, and the protoc/grpc_cpp_plugin
dnl code-generation tools
dnl
dnl sets:
dnl   want_apm  - "yes"/"no", whether --with-apm was requested
dnl   found_apm - "yes"/"no", whether all required pieces were located
dnl   APM_CFLAGS, APM_LIBS - AC_SUBST'd build flags when found_apm=yes
dnl
AC_DEFUN([APM_CHECK_CONFIG],
[
	AC_ARG_WITH([apm],[
If you want to use APM collector in proxy:
AS_HELP_STRING([--with-apm],[turn on OTLP/gRPC event collector, requires --enable-proxy @<:@default=no@:>@])],
	[
		if test "x$withval" = "xno"; then
			want_apm="no"
		else
			want_apm="yes"
		fi
	],
	[want_apm="no"])

	found_apm="no"

	if test "x$want_apm" = "xyes"; then

		AC_LANG_PUSH([C++])
		AX_CXX_COMPILE_STDCXX([17], [noext], [mandatory])
		AC_LANG_POP([C++])

		m4_ifdef([PKG_PROG_PKG_CONFIG], [PKG_PROG_PKG_CONFIG()])
		if test -z "$PKG_CONFIG"; then
			AC_MSG_ERROR([pkg-config is required by --with-apm but was not found. Please install pkg-config/pkgconf.])
		fi

		m4_ifdef([PKG_CHECK_MODULES], [
			PKG_CHECK_MODULES([GRPC], [grpc++ >= 1.40])
			PKG_CHECK_MODULES([PROTOBUF], [protobuf])
		], [
			AC_MSG_ERROR([--with-apm needs pkg-config autoconf macros (pkg.m4); install pkg-config/pkgconf and re-run autoreconf])
		])

		dnl build-time codegen tools
		AC_ARG_VAR([PROTOC], [Path to the protoc compiler])
		AC_PATH_PROG([PROTOC], [protoc], [no])
		AS_IF([test "x$PROTOC" = "xno"],
			[AC_MSG_ERROR([protoc not found; required by --with-apm. Install protobuf-compiler (or equivalent) and re-run configure.])])

		AC_ARG_VAR([GRPC_CPP_PLUGIN], [Path to the grpc_cpp_plugin protoc plugin])
		AC_PATH_PROG([GRPC_CPP_PLUGIN], [grpc_cpp_plugin], [no])
		AS_IF([test "x$GRPC_CPP_PLUGIN" = "xno"],
			[AC_MSG_ERROR([grpc_cpp_plugin not found; required by --with-apm. Install grpc-cpp-plugin/grpc-plugins (or equivalent) and re-run configure.])])

		dnl sanity-check protoc version matches libprotobuf
		protoc_version=`$PROTOC --version | sed 's/^libprotoc //'`
		AC_MSG_NOTICE([using protoc $protoc_version at $PROTOC])
		AC_MSG_NOTICE([using grpc_cpp_plugin at $GRPC_CPP_PLUGIN])

		APM_CFLAGS="$GRPC_CFLAGS $PROTOBUF_CFLAGS"
		APM_LIBS="$GRPC_LIBS $PROTOBUF_LIBS -lstdc++"

		found_apm="yes"
	fi

	AC_SUBST(APM_CFLAGS)
	AC_SUBST(APM_LIBS)
])
