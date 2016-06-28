/* Getopt for GNU.
   NOTE: getopt is now part of the C library, so if you don't know what
   "Keep this file name-space clean" means, talk to roland@gnu.ai.mit.edu
   before changing it!

   Copyright (C) 1987, 88, 89, 90, 91, 92, 1993
        Free Software Foundation, Inc.

   This program is free software; you can redistribute it and/or modify it
   under the terms of the GNU General Public License as published by the
   Free Software Foundation; either version 2, or (at your zbx_option) any
   later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.  */


#include "common.h"


/* If GETOPT_COMPAT is defined, `+' as well as `--' can introduce a
   long-named zbx_option.  Because this is not POSIX.2 compliant, it is
   being phased out.  */
/* #define GETOPT_COMPAT */
#undef GETOPT_COMPAT

/* This version of `getopt' appears to the caller like standard Unix `getopt'
   but it behaves differently for the user, since it allows the user
   to intersperse the options with the other arguments.

   As `getopt' works, it permutes the elements of ARGV so that,
   when it is done, all the options precede everything else.  Thus
   all application programs are extended to handle flexible argument order.

   Setting the environment variable POSIXLY_CORRECT disables permutation.
   Then the behavior is completely standard.

   GNU application programs can use a third alternative mode in which
   they can distinguish the relative order of options and other arguments.  */

#include "zbxgetopt.h"

#undef BAD_OPTION

/* For communication from `getopt' to the caller.
   When `getopt' finds an zbx_option that takes an argument,
   the argument value is returned here.
   Also, when `ordering' is RETURN_IN_ORDER,
   each non-zbx_option ARGV-element is returned here.  */

char *zbx_optarg = NULL;

/* Index in ARGV of the next element to be scanned.
   This is used for communication to and from the caller
   and for communication between successive calls to `getopt'.

   On entry to `getopt', zero means this is the first call; initialize.

   When `getopt' returns EOF, this is the index of the first of the
   non-zbx_option elements that the caller should itself scan.

   Otherwise, `zbx_optind' communicates from one call to the next
   how much of ARGV has been scanned so far.  */

/* XXX 1003.2 says this must be 1 before any call.  */
int zbx_optind = 0;

/* The next char to be scanned in the zbx_option-element
   in which the last zbx_option character we returned was found.
   This allows us to pick up the scan where we left off.

   If this is zero, or a null string, it means resume the scan
   by advancing to the next ARGV-element.  */

static char *nextchar;

/* Callers store zero here to inhibit the error message
   for unrecognized options.  */

int zbx_opterr = 1;

/* Set to an zbx_option character which was unrecognized.
   This must be initialized on some systems to avoid linking in the
   system's own getopt implementation.  */

#define BAD_OPTION '\0'
int zbx_optopt = BAD_OPTION;

/* Describe how to deal with options that follow non-zbx_option ARGV-elements.

   If the caller did not specify anything,
   the default is REQUIRE_ORDER if the environment variable
   POSIXLY_CORRECT is defined, PERMUTE otherwise.

   REQUIRE_ORDER means don't recognize them as options;
   stop zbx_option processing when the first non-zbx_option is seen.
   This is what Unix does.
   This mode of operation is selected by either setting the environment
   variable POSIXLY_CORRECT, or using `+' as the first character
   of the list of zbx_option characters.

   PERMUTE is the default.  We permute the contents of ARGV as we scan,
   so that eventually all the non-options are at the end.  This allows options
   to be given in any order, even with programs that were not written to
   expect this.

   RETURN_IN_ORDER is an zbx_option available to programs that were written
   to expect options and other ARGV-elements in any order and that care about
   the ordering of the two.  We describe each non-zbx_option ARGV-element
   as if it were the argument of an zbx_option with character code 1.
   Using `-' as the first character of the list of zbx_option characters
   selects this mode of operation.

   The special argument `--' forces an end of zbx_option-scanning regardless
   of the value of `ordering'.  In the case of RETURN_IN_ORDER, only
   `--' can cause `getopt' to return EOF with `zbx_optind' != ARGC.  */

static enum
{
  REQUIRE_ORDER, PERMUTE, RETURN_IN_ORDER
} ordering;

/* Handle permutation of arguments.  */

/* Describe the part of ARGV that contains non-options that have
   been skipped.  `first_nonopt' is the index in ARGV of the first of them;
   `last_nonopt' is the index after the last of them.  */

static int first_nonopt;
static int last_nonopt;

/* Exchange two adjacent subsequences of ARGV.
   One subsequence is elements [first_nonopt,last_nonopt)
   which contains all the non-options that have been skipped so far.
   The other is elements [last_nonopt,zbx_optind), which contains all
   the options processed since those non-options were skipped.

   `first_nonopt' and `last_nonopt' are relocated so that they describe
   the new indices of the non-options in ARGV after they are moved.

   To perform the swap, we first reverse the order of all elements. So
   all options now come before all non options, but they are in the
   wrong order. So we put back the options and non options in original
   order by reversing them again. For example:
       original input:      a b c -x -y
       reverse all:         -y -x c b a
       reverse options:     -x -y c b a
       reverse non options: -x -y a b c
*/


static void exchange (char **argv)
{
  char *temp; char **first, **last;

  /* Reverse all the elements [first_nonopt, zbx_optind) */
  first = &argv[first_nonopt];
  last  = &argv[zbx_optind-1];
  while (first < last) {
    temp = *first; *first = *last; *last = temp; first++; last--;
  }
  /* Put back the options in order */
  first = &argv[first_nonopt];
  first_nonopt += (zbx_optind - last_nonopt);
  last  = &argv[first_nonopt - 1];
  while (first < last) {
    temp = *first; *first = *last; *last = temp; first++; last--;
  }

  /* Put back the non options in order */
  first = &argv[first_nonopt];
  last_nonopt = zbx_optind;
  last  = &argv[last_nonopt-1];
  while (first < last) {
    temp = *first; *first = *last; *last = temp; first++; last--;
  }
}

/* Scan elements of ARGV (whose length is ARGC) for zbx_option characters
   given in OPTSTRING.

   If an element of ARGV starts with '-', and is not exactly "-" or "--",
   then it is an zbx_option element.  The characters of this element
   (aside from the initial '-') are zbx_option characters.  If `getopt'
   is called repeatedly, it returns successively each of the zbx_option characters
   from each of the zbx_option elements.

   If `getopt' finds another zbx_option character, it returns that character,
   updating `zbx_optind' and `nextchar' so that the next call to `getopt' can
   resume the scan with the following zbx_option character or ARGV-element.

   If there are no more zbx_option characters, `getopt' returns `EOF'.
   Then `zbx_optind' is the index in ARGV of the first ARGV-element
   that is not an zbx_option.  (The ARGV-elements have been permuted
   so that those that are not options now come last.)

   OPTSTRING is a string containing the legitimate zbx_option characters.
   If an zbx_option character is seen that is not listed in OPTSTRING,
   return BAD_OPTION after printing an error message.  If you set `zbx_opterr' to
   zero, the error message is suppressed but we still return BAD_OPTION.

   If a char in OPTSTRING is followed by a colon, that means it wants an arg,
   so the following text in the same ARGV-element, or the text of the following
   ARGV-element, is returned in `zbx_optarg'.  Two colons mean an zbx_option that
   wants an optional arg; if there is text in the current ARGV-element,
   it is returned in `zbx_optarg', otherwise `zbx_optarg' is set to zero.

   If OPTSTRING starts with `-' or `+', it requests different methods of
   handling the non-zbx_option ARGV-elements.
   See the comments about RETURN_IN_ORDER and REQUIRE_ORDER, above.

   Long-named options begin with `--' instead of `-'.
   Their names may be abbreviated as long as the abbreviation is unique
   or is an exact match for some defined zbx_option.  If they have an
   argument, it follows the zbx_option name in the same ARGV-element, separated
   from the zbx_option name by a `=', or else the in next ARGV-element.
   When `getopt' finds a long-named zbx_option, it returns 0 if that zbx_option's
   `flag' field is nonzero, the value of the zbx_option's `val' field
   if the `flag' field is zero.

   LONGOPTS is a vector of `struct zbx_option' terminated by an
   element containing a name which is zero.

   LONGIND returns the index in LONGOPT of the long-named zbx_option found.
   It is only valid when a long-named zbx_option has been found by the most
   recent call.

   If LONG_ONLY is nonzero, '-' as well as '--' can introduce
   long-named options.  */

static int zbx_getopt_internal (int argc, char **argv, const char *optstring,
                 const struct zbx_option *longopts, int *longind,
                 int long_only)
{
  static char empty_string[1];
  int option_index;

  if (longind != NULL)
    *longind = -1;

  zbx_optarg = 0;

  /* Initialize the internal data when the first call is made.
     Start processing options with ARGV-element 1 (since ARGV-element 0
     is the program name); the sequence of previously skipped
     non-zbx_option ARGV-elements is empty.  */

  if (zbx_optind == 0)
    {
      first_nonopt = last_nonopt = zbx_optind = 1;

      nextchar = NULL;

      /* Determine how to handle the ordering of options and nonoptions.  */

      if (optstring[0] == '-')
        {
          ordering = RETURN_IN_ORDER;
          ++optstring;
        }
      else if (optstring[0] == '+')
        {
          ordering = REQUIRE_ORDER;
          ++optstring;
        }
#if OFF
      else if (getenv ("POSIXLY_CORRECT") != NULL)
        ordering = REQUIRE_ORDER;
#endif
      else
        ordering = PERMUTE;
    }

  if (nextchar == NULL || *nextchar == '\0')
    {
      if (ordering == PERMUTE)
        {
          /* If we have just processed some options following some non-options,
             exchange them so that the options come first.  */

          if (first_nonopt != last_nonopt && last_nonopt != zbx_optind)
            exchange (argv);
          else if (last_nonopt != zbx_optind)
            first_nonopt = zbx_optind;

          /* Now skip any additional non-options
             and extend the range of non-options previously skipped.  */

          while (zbx_optind < argc
                 && (argv[zbx_optind][0] != '-' || argv[zbx_optind][1] == '\0')
#ifdef GETOPT_COMPAT
                 && (longopts == NULL
                     || argv[zbx_optind][0] != '+' || argv[zbx_optind][1] == '\0')
#endif                          /* GETOPT_COMPAT */
                 )
            zbx_optind++;
          last_nonopt = zbx_optind;
        }

      /* Special ARGV-element `--' means premature end of options.
         Skip it like a null zbx_option,
         then exchange with previous non-options as if it were an zbx_option,
         then skip everything else like a non-zbx_option.  */

      if (zbx_optind != argc && !strcmp (argv[zbx_optind], "--"))
        {
          zbx_optind++;

          if (first_nonopt != last_nonopt && last_nonopt != zbx_optind)
            exchange (argv);
          else if (first_nonopt == last_nonopt)
            first_nonopt = zbx_optind;
          last_nonopt = argc;

          zbx_optind = argc;
        }

      /* If we have done all the ARGV-elements, stop the scan
         and back over any non-options that we skipped and permuted.  */

      if (zbx_optind == argc)
        {
          /* Set the next-arg-index to point at the non-options
             that we previously skipped, so the caller will digest them.  */
          if (first_nonopt != last_nonopt)
            zbx_optind = first_nonopt;
          return EOF;
        }

      /* If we have come to a non-zbx_option and did not permute it,
         either stop the scan or describe it to the caller and pass it by.  */

      if ((argv[zbx_optind][0] != '-' || argv[zbx_optind][1] == '\0')
#ifdef GETOPT_COMPAT
          && (longopts == NULL
              || argv[zbx_optind][0] != '+' || argv[zbx_optind][1] == '\0')
#endif                          /* GETOPT_COMPAT */
          )
        {
          if (ordering == REQUIRE_ORDER)
            return EOF;
          zbx_optarg = argv[zbx_optind++];
          return 1;
        }

      /* We have found another zbx_option-ARGV-element.
         Start decoding its characters.  */

      nextchar = (argv[zbx_optind] + 1
                  + (longopts != NULL && argv[zbx_optind][1] == '-'));
    }

  if (longopts != NULL
      && ((argv[zbx_optind][0] == '-'
           && (argv[zbx_optind][1] == '-' || long_only))
#ifdef GETOPT_COMPAT
          || argv[zbx_optind][0] == '+'
#endif                          /* GETOPT_COMPAT */
          ))
    {
      const struct zbx_option *p;
      char *s = nextchar;
      int exact = 0;
      int ambig = 0;
      const struct zbx_option *pfound = NULL;
      int indfound = 0;
      int needexact = 0;

#if ON
      /* allow `--zbx_option#value' because you cannout assign a '='
         to an environment variable under DOS command.com */
      while (*s && *s != '=' && * s != '#')
        s++;
#else
      while (*s && *s != '=')
        s++;
#endif

      /* Test all options for either exact match or abbreviated matches.  */
      for (p = longopts, option_index = 0; p->name;
           p++, option_index++)
        if (!strncmp (p->name, nextchar, (unsigned) (s - nextchar)))
          {
            if (p->has_arg & 0x10)
              needexact = 1;
            if ((unsigned) (s - nextchar) == strlen (p->name))
              {
                /* Exact match found.  */
                pfound = p;
                indfound = option_index;
                exact = 1;
                break;
              }
            else if (pfound == NULL)
              {
                /* First nonexact match found.  */
                pfound = p;
                indfound = option_index;
              }
            else
              /* Second nonexact match found.  */
              ambig = 1;
          }

      /* don't allow nonexact longoptions */
      if (needexact && !exact)
        {
          if (zbx_opterr)
                zbx_error("unrecognized zbx_option `%s'", argv[zbx_optind]);

          nextchar += strlen (nextchar);
          zbx_optind++;
          return BAD_OPTION;
        }
      if (ambig && !exact)
        {
          if (zbx_opterr)
                zbx_error("zbx_option `%s' is ambiguous", argv[zbx_optind]);

          nextchar += strlen (nextchar);
          zbx_optind++;
          return BAD_OPTION;
        }

      if (pfound != NULL)
        {
          int have_arg = (s[0] != '\0');
          if (have_arg && (pfound->has_arg & 0xf))
            have_arg = (s[1] != '\0');
          option_index = indfound;
          zbx_optind++;
          if (have_arg)
            {
              /* Don't test has_arg with >, because some C compilers don't
                 allow it to be used on enums.  */
              if (pfound->has_arg & 0xf)
                zbx_optarg = s + 1;
              else
                {
                  if (zbx_opterr)
                    {
                      if (argv[zbx_optind - 1][1] == '-')
                        /* --zbx_option */
                        zbx_error("zbx_option `--%s' doesn't allow an argument",pfound->name);
                      else
                        /* +zbx_option or -zbx_option */
                        zbx_error("zbx_option `%c%s' doesn't allow an argument", argv[zbx_optind - 1][0], pfound->name);
                    }
                  nextchar += strlen (nextchar);
                  return BAD_OPTION;
                }
            }
          else if ((pfound->has_arg & 0xf) == 1)
            {
#if OFF
              if (zbx_optind < argc)
#else
              if (zbx_optind < argc && (pfound->has_arg & 0x20) == 0)
#endif
                zbx_optarg = argv[zbx_optind++];
              else
                {
                  if (zbx_opterr)
                    zbx_error("zbx_option `--%s%s' requires an argument",
                             pfound->name, (pfound->has_arg & 0x20) ? "=" : "");
                  nextchar += strlen (nextchar);
                  return optstring[0] == ':' ? ':' : BAD_OPTION;
                }
            }
          nextchar += strlen (nextchar);
          if (longind != NULL)
            *longind = option_index;
          if (pfound->flag)
            {
              *(pfound->flag) = pfound->val;
              return 0;
            }
          return pfound->val;
        }
      /* Can't find it as a long zbx_option.  If this is not getopt_long_only,
         or the zbx_option starts with '--' or is not a valid short
         zbx_option, then it's an error.
         Otherwise interpret it as a short zbx_option.  */
      if (!long_only || argv[zbx_optind][1] == '-'
#ifdef GETOPT_COMPAT
          || argv[zbx_optind][0] == '+'
#endif                          /* GETOPT_COMPAT */
          || strchr (optstring, *nextchar) == NULL)
        {
          if (zbx_opterr)
            {
              if (argv[zbx_optind][1] == '-')
                /* --zbx_option */
                zbx_error("unrecognized zbx_option `--%s'", nextchar);
              else
                /* +zbx_option or -zbx_option */
                zbx_error("unrecognized zbx_option `%c%s'", argv[zbx_optind][0], nextchar);
            }
          nextchar = empty_string;
          zbx_optind++;
          return BAD_OPTION;
        }
        (void) &ambig;  /* UNUSED */
    }

  /* Look at and handle the next zbx_option-character.  */

  {
    char c = *nextchar++;
    const char *temp = strchr (optstring, c);

    /* Increment `zbx_optind' when we start to process its last character.  */
    if (*nextchar == '\0')
      ++zbx_optind;

    if (temp == NULL || c == ':')
      {
        if (zbx_opterr)
          {
#if OFF
            if (c < 040 || c >= 0177)
              zbx_error("unrecognized zbx_option, character code 0%o", c);
            else
              zbx_error("unrecognized zbx_option `-%c'", c);
#else
            /* 1003.2 specifies the format of this message.  */
            zbx_error("illegal zbx_option -- %c", c);
#endif
          }
        zbx_optopt = c;
        return BAD_OPTION;
      }
    if (temp[1] == ':')
      {
        if (temp[2] == ':')
          {
            /* This is an zbx_option that accepts an argument optionally.  */
            if (*nextchar != '\0')
              {
                zbx_optarg = nextchar;
                zbx_optind++;
              }
            else
              zbx_optarg = 0;
            nextchar = NULL;
          }
        else
          {
            /* This is an zbx_option that requires an argument.  */
            if (*nextchar != '\0')
              {
                zbx_optarg = nextchar;
                /* If we end this ARGV-element by taking the rest as an arg,
                   we must advance to the next element now.  */
                zbx_optind++;
              }
            else if (zbx_optind == argc)
              {
                if (zbx_opterr)
                  {
#if OFF
                    zbx_error("zbx_option `-%c' requires an argument", c);
#else
                    /* 1003.2 specifies the format of this message.  */
                    zbx_error("zbx_option requires an argument -- %c", c);
#endif
                  }
                zbx_optopt = c;
                if (optstring[0] == ':')
                  c = ':';
                else
                  c = BAD_OPTION;
              }
            else
              /* We already incremented `zbx_optind' once;
                 increment it again when taking next ARGV-elt as argument.  */
              zbx_optarg = argv[zbx_optind++];
            nextchar = NULL;
          }
      }
    return c;
  }
}

int zbx_getopt(int argc, char **argv, const char *optstring)
{
  return zbx_getopt_internal (argc, argv, optstring,
                           (const struct zbx_option *) 0,
                           (int *) 0,
                           0);
}

int zbx_getopt_long(int argc, char **argv, const char *options,
                    const struct zbx_option *long_options, int *opt_index)
{
  return zbx_getopt_internal (argc, argv, options, long_options, opt_index, 0);
}


#ifdef TEST2

/* Compile with -DTEST to make an executable for use in testing
   the above definition of `getopt'.  */

int
main (argc, argv)
     int argc;
     char **argv;
{
  int c;
  int digit_optind = 0;

  while (1)
    {
      int this_option_optind = zbx_optind ? zbx_optind : 1;

      c = getopt (argc, argv, "abc:d:0123456789");
      if (c == EOF)
        break;

      switch (c)
        {
        case '0':
        case '1':
        case '2':
        case '3':
        case '4':
        case '5':
        case '6':
        case '7':
        case '8':
        case '9':
          if (digit_optind != 0 && digit_optind != this_option_optind)
            printf ("digits occur in two different argv-elements.\n");
          digit_optind = this_option_optind;
          printf ("zbx_option %c\n", c);
          break;

        case 'a':
          printf ("zbx_option a\n");
          break;

        case 'b':
          printf ("zbx_option b\n");
          break;

        case 'c':
          printf ("zbx_option c with value `%s'\n", zbx_optarg);
          break;

        case BAD_OPTION:
          break;

        default:
          printf ("?? getopt returned character code 0%o ??\n", c);
        }
    }

  if (zbx_optind < argc)
    {
      printf ("non-zbx_option ARGV-elements: ");
      while (zbx_optind < argc)
        printf ("%s ", argv[zbx_optind++]);
      printf ("\n");
    }

  exit (0);
}

#endif /* TEST */
