/*
NOTE: this file was modified by Zabbix

Declarations for getopt.
Copyright (C) 1989, 1990, 1991, 1992, 1993 Free Software Foundation, Inc.

This program is free software; you can redistribute it and/or modify it
under the terms of the GNU General Public License as published by the
Free Software Foundation; either version 2, or (at your option) any
later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

#ifndef ZBX_GETOPT_H
#define ZBX_GETOPT_H 1

#ifdef __cplusplus
extern "C" {
#endif

/*
Describe the long-named options requested by the application.
The LONG_OPTIONS argument to getopt_long or getopt_long_only is a vector
of `struct option' terminated by an element containing a name which is
zero.

The field `has_arg' is:
no_argument          (or 0) if the option does not take an argument,
required_argument    (or 1) if the option requires an argument,
optional_argument    (or 2) if the option takes an optional argument.

If the field `flag' is not NULL, it points to a variable that is set
to the value given in the field `val' when the option is found, but
left unchanged if the option is not found.

To have a long-named option do something other than set an `int' to
a compiled-in constant, such as set a value from `optarg', set the
option's `flag' field to zero and its `val' field to a non-zero
value (the equivalent single-letter option character, if there is
one).  For long options that have a zero `flag' field, `getopt'
returns the contents of the `val' field.
*/

struct zbx_option
{
	const char	*name;
	/*
	has_arg can't be an enum because some compilers complain about
	type mismatches in all the code that assumes it is an int.
	*/
	int		has_arg;
	int		*flag;
	int		val;
};

int	zbx_getopt_long(int argc, char **argv, const char *options, const struct zbx_option *long_options,
		int *opt_index, char **zbx_optarg, int *zbx_optind);

#ifdef __cplusplus
}
#endif

#endif /* ZBX_GETOPT_H */
