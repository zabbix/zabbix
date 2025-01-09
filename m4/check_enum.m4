# Copyright (C) 2001-2025 Zabbix SIA
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

AC_DEFUN([ENUM_CHECK], [
  AC_MSG_CHECKING([for defined $1])

  AS_VAR_PUSHDEF([enum_var], [enum_var_have_$1])

  AC_LINK_IFELSE([AC_LANG_PROGRAM([[
    #include <$2>
  ]], [[
    int e = $1;
  ]])],
  [AS_VAR_SET([enum_var], [yes])],
  [AS_VAR_SET([enum_var], [no])])

  AS_IF([test yes = AS_VAR_GET([enum_var])],[
    AC_DEFINE_UNQUOTED(AS_TR_CPP(HAVE_$1), 1, [Define to 1 if $1 definition is available])
    AC_MSG_RESULT(yes)
  ], [
    AC_MSG_RESULT(no)
  ])

  AS_VAR_POPDEF([enum_var])
])dnl
