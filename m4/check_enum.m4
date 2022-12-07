##
# ----------------------------------------------------------
# SYNOPSIS
#
#   ENUM_CHECK(ENUM,INCLUDE)
#
# DESCRIPTION
#
#   Checks if certain enumerator (or macro) constant exists
#   in a header and defines C macro with prefix HAVE_.
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([ENUM_CHECK], [
  AC_MSG_CHECKING([for enum constant $1])

  AS_VAR_PUSHDEF([enum_var], [enum_var_have_$1])

  AC_LINK_IFELSE([AC_LANG_PROGRAM([[
    #include <$2>
  ]], [[
    int e = $1;
  ]])],
  [AS_VAR_SET([enum_var], [yes])],
  [AS_VAR_SET([enum_var], [no])])

  AS_IF([test yes = AS_VAR_GET([enum_var])],[
    AC_DEFINE_UNQUOTED(AS_TR_CPP(HAVE_$1), 1, [Define to 1 if the library has the $1 enum value])
    AC_MSG_RESULT(yes)
  ], [
    AC_MSG_RESULT(no)
  ])

  AS_VAR_POPDEF([enum_var])
])dnl
