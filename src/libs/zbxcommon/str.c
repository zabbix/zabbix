/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "threads.h"

/******************************************************************************
 *                                                                            *
 * Function: app_title                                                        *
 *                                                                            *
 * Purpose: print title of application on stdout                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  title_message - is global variable which must be initialized    *
 *                            in each zabbix application                      *
 *                                                                            *
 ******************************************************************************/
static void app_title()
{
	printf("%s v%s (%s)\n", title_message, ZABBIX_VERSION, ZABBIX_REVDATE);
}

/******************************************************************************
 *                                                                            *
 * Function: version                                                          *
 *                                                                            *
 * Purpose: print version and compilation time of application on stdout       *
 *          by application request with parameter '-v'                        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
void version()
{
	app_title();
	printf("Compilation time:  %s %s\n", __DATE__, __TIME__);
}

/******************************************************************************
 *                                                                            *
 * Function: usage                                                            *
 *                                                                            *
 * Purpose: print applicatin parameters on stdout                             *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  usage_message - is global variable which must be initialized    *
 *                            in each zabbix application                      *
 *                                                                            *
 ******************************************************************************/
void usage()
{
	printf("usage: %s %s\n", progname, usage_message);
}

/******************************************************************************
 *                                                                            *
 * Function: help                                                             *
 *                                                                            *
 * Purpose: print help of applicatin parameters on stdout by application      *
 *          request with parameter '-h'                                       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  help_message - is global variable which must be initialized     *
 *                            in each zabbix application                      *
 *                                                                            *
 ******************************************************************************/
void help()
{
	char **p = help_message;
	
	app_title();
	printf("\n");
	usage();
	printf("\n");
	while (*p) printf("%s\n", *p++);
}

/******************************************************************************
 *                                                                            *
 * Function: find_char                                                        *
 *                                                                            *
 * Purpose: locate a character in the string                                  *
 *                                                                            *
 * Parameters: str - string                                                   *
 *             c   - character to find                                        *
 *                                                                            *
 * Return value:  position of the character                                   *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! beter use system functions like 'strchr' !!!                 *
 *                                                                            *
 ******************************************************************************/
int	find_char(char *str,char c)
{
	char *p;
	for(p = str; *p; p++) 
		if(*p == c) return (int)(p - str);

	return	FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_error                                                        *
 *                                                                            *
 * Purpose: Print error text to the stderr                                    *
 *                                                                            *
 * Parameters: fmt - format of mesage                                         *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
/* #define ZBX_STDERR_FILE "zbx_errors.log" */

void zbx_error(const char *fmt, ...)
{
	va_list args;
	FILE *f = NULL;

#if defined(ZBX_STDERR_FILE)
	f = fopen(ZBX_STDERR_FILE,"a+");
#else
	f = stderr;
#endif /* ZBX_STDERR_FILE */
    
	va_start(args, fmt);

	fprintf(f, "%s [%li]: ",progname, zbx_get_thread_id());
	vfprintf(f, fmt, args);
	fprintf(f, "\n");
	fflush(f);

	va_end(args);

#if defined(ZBX_STDERR_FILE)
	zbx_fclose(f);
#endif /* ZBX_STDERR_FILE */
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_snprintf                                                     *
 *                                                                            *
 * Purpose: Sequire version of snprintf function.                             *
 *          Add zero character at the end of string.                          *
 *                                                                            *
 * Parameters: str - distination buffer poiner                                *
 *             count - size of distination buffer                             *
 *             fmt - format                                                   *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
int zbx_snprintf(char* str, size_t count, const char *fmt, ...)
{
	va_list	args;
	int	writen_len = 0;
    
	assert(str);

	va_start(args, fmt);

	writen_len = vsnprintf(str, count, fmt, args);
	writen_len = MIN(writen_len, ((int)count) - 1);
	writen_len = MAX(writen_len, 0);

	str[writen_len] = '\0';

	va_end(args);

	return writen_len;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vsnprintf                                                    *
 *                                                                            *
 * Purpose: Sequire version of vsnprintf function.                            *
 *          Add zero character at the end of string.                          *
 *                                                                            *
 * Parameters: str - distination buffer poiner                                *
 *             count - size of distination buffer                             *
 *             fmt - format                                                   *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev (see also zbx_snprintf)                           *
 *                                                                            *
 ******************************************************************************/
int zbx_vsnprintf(char* str, size_t count, const char *fmt, va_list args)
{
	int	writen_len = 0;

	assert(str);

	writen_len = vsnprintf(str, count, fmt, args);
	writen_len = MIN(writen_len, ((int)count) - 1);
	writen_len = MAX(writen_len, 0);

	str[writen_len] = '\0';

	return writen_len;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_snprintf_alloc                                               *
 *                                                                            *
 * Purpose: Sequire version of snprintf function.                             *
 *          Add zero character at the end of string.                          *
 *          Reallocs memory if not enough.                                    *
 *                                                                            *
 * Parameters: str - distination buffer poiner                                *
 *             alloc_len - already allocated memory                           *
 *             offset - ofsset for writing                                    *
 *             max_len - fmt + data won't write more than max_len bytes       *
 *             fmt - format                                                   *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
void zbx_snprintf_alloc(char **str, int *alloc_len, int *offset, int max_len, const char *fmt, ...)
{
	va_list	args;

	assert(str);
	assert(*str);

	assert(alloc_len);
	assert(offset);

	assert(fmt);

	va_start(args, fmt);

	if(*offset + max_len >= *alloc_len)
	{
		*str = zbx_realloc(*str, (*alloc_len)+64*max_len);
		*alloc_len += 64*max_len;
	}

	*offset += zbx_vsnprintf(*str+*offset, max_len, fmt, args);

	va_end(args);
}


/* Has to be rewritten to avoi malloc */
char *string_replace(char *str, char *sub_str1, char *sub_str2)
{
        char *new_str;
        char *p;
        char *q;
        char *r;
        char *t;
        long len;
        long diff;
        unsigned long count = 0;

	assert(str);
	assert(sub_str1);
	assert(sub_str2);

        len = (long)strlen(sub_str1);

        /* count the number of occurances of sub_str1 */
        for ( p=str; (p = strstr(p, sub_str1)); p+=len, count++ );

	if ( 0 == count )	return strdup(str);

        diff = (long)strlen(sub_str2) - len;

        /* allocate new memory */
        new_str = zbx_malloc((size_t)(strlen(str) + count*diff + 1)*sizeof(char));

        for (q=str,t=new_str,p=str; (p = strstr(p, sub_str1)); )
        {
                /* copy until next occurance of sub_str1 */
                for ( ; q < p; *t++ = *q++);
                q += len;
                p = q;
                for ( r = sub_str2; (*t++ = *r++); );
                --t;
        }
        /* copy the tail of str */
        for( ; *q ; *t++ = *q++ );

	*t = '\0';
	
        return new_str;

}

/******************************************************************************
 *                                                                            *
 * Function: del_zeroes                                                       *
 *                                                                            *
 * Purpose: delete all right '0' and '.' for the string                       *
 *                                                                            *
 * Parameters: s - string to trim '0'                                         *
 *                                                                            *
 * Return value: string without right '0'                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:  10.0100 => 10.01, 10. => 10                                                                 *
 *                                                                            *
 ******************************************************************************/
void del_zeroes(char *s)
{
	int     i;

	if(strchr(s,'.')!=NULL)
	{
		for(i = (int)strlen(s)-1;;i--)
		{
			if(s[i]=='0')
			{
				s[i]=0;
			}
			else if(s[i]=='.')
			{
				s[i]=0;
				break;
			}
			else
			{
				break;
			}
		}
	}
}


/******************************************************************************
 *                                                                            *
 * Function: get_param                                                        *
 *                                                                            *
 * Purpose: return parameter by index (num) from parameter list (param)       *
 *                                                                            *
 * Parameters:                                                                *
 * 	param  - parameter list                                               *
 *      num    - requested parameter index                                    *
 *      buf    - pointer of output buffer                                     *
 *      maxlem - size of output buffer                                        *
 *                                                                            *
 * Return value:                                                              *
 *      1 - requested parameter missed                                        *
 *      0 - requested parameter founded (value - 'buf' can be empty string)   *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  delimeter vor parameters is ','                                 *
 *                                                                            *
 ******************************************************************************/
int	get_param(const char *param, int num, char *buf, int maxlen)
{
	char	tmp[MAX_STRING_LEN];
	char	*s;
	int	ret = 1;
	int	i = 0;
	int	idx = 0;

	strscpy(tmp,param);

	s = &tmp[0];
	
	for(i=0; tmp[i] != '\0'; i++)
	{
		if(tmp[i] == ',')
		{
			idx++;
			if(idx == num)
			{
				tmp[i]='\0';
				zbx_strlcpy(buf, s, maxlen);
				tmp[i]=','; /* restore source string */
				ret = 0;
				break;
				
			}
			s = &tmp[i+1];
		}
	}

	if(ret != 0)
	{
		idx++;
		if(idx == num)
		{
			zbx_strlcpy(buf, s, maxlen);
			ret = 0;
		}
	}
	
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: num_param                                                        *
 *                                                                            *
 * Purpose: calculate count of parameters from parameter list (param)         *
 *                                                                            *
 * Parameters:                                                                *
 * 	param  - parameter list                                               *
 *                                                                            *
 * Return value: count of parameters                                          *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  delimeter vor parameters is ','                                 *
 *                                                                            *
 ******************************************************************************/
int	num_param(const char *param)
{
	int	i;
	int	ret = 1;

	if(param == NULL) 
		return 0;
	
	for(i=0;param[i]!=0;i++)
	{
		if(param[i]==',')	ret++;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: delete_reol                                                      *
 *                                                                            *
 * Purpose: delete all right EOL characters                                   *
 *                                                                            *
 * Parameters: c - string to delete EOL                                       *
 *                                                                            *
 * Return value:  the string wtihout EOL                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	delete_reol(char *c)
{
	int i;

	for(i=(int)strlen(c)-1;i>=0;i--)
	{
		if( c[i] != '\n')	break;
		c[i]=0;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_rtrim                                                        *
 *                                                                            *
 * Purpose: Strip haracters from the end of a string                          *
 *                                                                            *
 * Parameters: str - string to processing                                     *
 *             charlist - null terminated list of characters                  *
 *                                                                            *
 * Return value: Stripped string                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_rtrim(char *str, const char *charlist)
{
	register char *p;

	if( !str || !charlist || !*str || !*charlist ) return;

	for(
		p = str + strlen(str) - 1;
		p >= str && NULL != strchr(charlist,*p);
		p--)
			*p = '\0';
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ltrim                                                        *
 *                                                                            *
 * Purpose: Strip haracters from the beginning of a string                    *
 *                                                                            *
 * Parameters: str - string to processing                                     *
 *             charlist - null terminated list of characters                  *
 *                                                                            *
 * Return value: Stripped string                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_ltrim(register char *str, const char *charlist)
{
	register char *p;

	if( !str || !charlist || !*str || !*charlist ) return;

	for( p = str; *p && NULL != strchr(charlist,*p); p++ );

	if( p == str )	return;
	
	while( *p )
	{
		*str = *p;
		str++;
		p++;
	}

	*str = '\0';
}

/******************************************************************************
 *                                                                            *
 * Function: rtrim_spaces                                                     *
 *                                                                            *
 * Purpose: delete all right spaces for the string                            *
 *                                                                            *
 * Parameters: c - string to trim spaces                                      *
 *                                                                            *
 * Return value: string without right spaces                                  *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	rtrim_spaces(char *c)
{
	int i,len;

	len = (int)strlen(c);
	for(i=len-1;i>=0;i--)
	{
		if( c[i] == ' ')
		{
			c[i]=0;
		}
		else	break;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: ltrim_spaces                                                     *
 *                                                                            *
 * Purpose: delete all left spaces for the string                             *
 *                                                                            *
 * Parameters: c - string to trim spaces                                      *
 *                                                                            *
 * Return value: string without left spaces                                   *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	ltrim_spaces(char *c)
{
	int i;
/* Number of left spaces */
	int spaces=0;

	for(i=0;c[i]!=0;i++)
	{
		if( c[i] == ' ')
		{
			spaces++;
		}
		else	break;
	}
	for(i=0;c[i+spaces]!=0;i++)
	{
		c[i]=c[i+spaces];
	}

	c[strlen(c)-spaces]=0;
}

/******************************************************************************
 *                                                                            *
 * Function: lrtrim_spaces                                                    *
 *                                                                            *
 * Purpose: delete all left and right spaces for the string                   *
 *                                                                            *
 * Parameters: c - string to trim spaces                                      *
 *                                                                            *
 * Return value: string without left and right spaces                         *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	lrtrim_spaces(char *c)
{
	ltrim_spaces(c);
	rtrim_spaces(c);
}


/******************************************************************************
 *                                                                            *
 * Function: zbx_get_field                                                    *
 *                                                                            *
 * Purpose: return Nth field of characted separated string                    *
 *                                                                            *
 * Parameters: c - string to trim spaces                                      *
 *                                                                            *
 * Return value: string without left and right spaces                         *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_field(char *line, char *result, int num, char separator)
{
	int delim=0;
	int ptr=0;
	int i;

	int ret = FAIL;

	for(i=0;line[i]!=0;i++)
	{
		if(line[i]==separator)
		{
			delim++;
			continue;
		}
		if(delim==num)
		{
			result[ptr++]=line[i];
			result[ptr]=0;
			ret = SUCCEED;
		}
	}
	return ret;
}

/*
 * Function: strlcpy, strlcat
 * Copyright (c) 1998 Todd C. Miller <Todd.Miller@courtesan.com>
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_strlcpy                                                      *
 *                                                                            *
 * Purpose: replacement of insecure strncpy, same os OpenBSD's strlcpy        *
 *                                                                            *
 * Copy src to string dst of size siz.  At most siz-1 characters              *
 * will be copied.  Always NUL terminates (unless siz == 0).                  *
 * Returns strlen(src); if retval >= siz, truncation occurred.                *
 *                                                                            *
 * Author: Todd C. Miller <Todd.Miller@courtesan.com>                         *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
size_t zbx_strlcpy(char *dst, const char *src, size_t siz)
{
	char *d = dst;
	const char *s = src;
	size_t n = siz;

	/* Copy as many bytes as will fit */
	if (n != 0) {
		while (--n != 0) {
			if ((*d++ = *s++) == '\0')
				break;
		}
	}

	/* Not enough room in dst, add NUL and traverse rest of src */
	if (n == 0) {
		if (siz != 0)
			*d = '\0';                /* NUL-terminate dst */
		while (*s++)
		;
	}

	return(s - src - 1);        /* count does not include NUL */
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strlcat                                                      *
 *                                                                            *
 * Purpose: replacement of insecure strncat, same os OpenBSD's strlcat        *
 *                                                                            *
 * Appends src to string dst of size siz (unlike strncat, size is the         *
 * full size of dst, not space left).  At most siz-1 characters               *
 * will be copied.  Always NUL terminates (unless siz <= strlen(dst)).        *
 * Returns strlen(src) + MIN(siz, strlen(initial dst)).                       *
 * If retval >= siz, truncation occurred.                                     *
 *                                                                            *
 * Author: Todd C. Miller <Todd.Miller@courtesan.com>                         *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 */
size_t zbx_strlcat(char *dst, const char *src, size_t siz)
{
	char *d = dst;
	const char *s = src;
	size_t n = siz;
	size_t dlen;

	/* Find the end of dst and adjust bytes left but don't go past end */
	while (n-- != 0 && *d != '\0')
		d++;
	dlen = d - dst;
	n = siz - dlen;

	if (n == 0)
		return(dlen + strlen(s));
	while (*s != '\0') {
		if (n != 1) {
			*d++ = *s;
			n--;
		}
		s++;
	}
	*d = '\0';

	return(dlen + (s - src));        /* count does not include NUL */
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dvsprintf                                                     *
 *                                                                            *
 * Purpose: dinamical formatted output conversion                             *
 *                                                                            *
 * Return value: formated string                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  required free allocated string with function 'zbx_free'         *
 *                                                                            *
 ******************************************************************************/
char* zbx_dvsprintf(char *dest, const char *f, va_list args)
{
	char	*string = NULL;
	int	n, size = MAX_STRING_LEN >> 1;

	va_list curr;

	while(1) {

		string = zbx_malloc(size);

		va_copy(curr, args);
		n = vsnprintf(string, size, f, curr);
		va_end(curr);

		if(n >= 0 && n < size)
			break;

		if(n >= size)	size = n + 1;
		else		size = size * 3 / 2 + 1;

		zbx_free(string);
	}

	if(dest) zbx_free(dest);

	return string;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dsprintf                                                     *
 *                                                                            *
 * Purpose: dinamical formatted output conversion                             *
 *                                                                            *
 * Return value: formated string                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  required free allocated string with function 'zbx_free'         *
 *                                                                            *
 ******************************************************************************/
char* zbx_dsprintf(char *dest, const char *f, ...)
{
	char	*string = NULL;
	va_list args;

	va_start(args, f);

	string = zbx_dvsprintf(dest, f, args);

	va_end(args);

	return string;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strdcat                                                      *
 *                                                                            *
 * Purpose: dinamical cating of strings                                       *
 *                                                                            *
 * Return value: new pointer of string                                        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  required free allocated string with function 'zbx_free'         *
 *            zbx_strdcat(NULL,"") must return "", not NULL!                  *
 *                                                                            *
 ******************************************************************************/
char* zbx_strdcat(char *dest, const char *src)
{
	register int new_len = 0;
	char *new_dest = NULL;

	if(!src)	return dest;
	if(!dest)	return strdup(src);
	
	new_len += (int)strlen(dest);
	new_len += (int)strlen(src);
	
	new_dest = zbx_malloc(new_len + 1);
	
	if(dest)
	{
		strcpy(new_dest, dest);
		strcat(new_dest, src);
		zbx_free(dest);
	}
	else
	{
		strcpy(new_dest, src);
	}

	new_dest[new_len] = '\0';

	return new_dest;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strdcat                                                      *
 *                                                                            *
 * Purpose: dinamical cating of formated strings                              *
 *                                                                            *
 * Return value: new pointer of string                                        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  required free allocated string with function 'zbx_free'         *
 *            zbx_strdcat(NULL,"") must return "", not NULL!                  *
 *                                                                            *
 ******************************************************************************/
char* zbx_strdcatf(char *dest, const char *f, ...)
{
	char *string = NULL;
	char *result = NULL;

	va_list args;

	va_start(args, f);

	string = zbx_dvsprintf(NULL, f, args);

	va_end(args);

	result = zbx_strdcat(dest, string);

	zbx_free(string);

	return result;
}
