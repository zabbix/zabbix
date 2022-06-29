/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "log.h"
#include "setproctitle.h"
#include "zbxthreads.h"

const int	INTERFACE_TYPE_PRIORITY[INTERFACE_TYPE_COUNT] =
{
	INTERFACE_TYPE_AGENT,
	INTERFACE_TYPE_SNMP,
	INTERFACE_TYPE_JMX,
	INTERFACE_TYPE_IPMI
};

static ZBX_THREAD_LOCAL volatile sig_atomic_t	zbx_timed_out;	/* 0 - no timeout occurred, 1 - SIGALRM took place */

#ifdef _WINDOWS

char	ZABBIX_SERVICE_NAME[ZBX_SERVICE_NAME_LEN] = APPLICATION_NAME;
char	ZABBIX_EVENT_SOURCE[ZBX_SERVICE_NAME_LEN] = APPLICATION_NAME;

int	__zbx_stat(const char *path, zbx_stat_t *buf)
{
	int	ret, fd;
	wchar_t	*wpath;

	wpath = zbx_utf8_to_unicode(path);

	if (-1 == (ret = _wstat64(wpath, buf)))
		goto out;

	if (0 != S_ISDIR(buf->st_mode) || 0 != buf->st_size)
		goto out;

	/* In the case of symlinks _wstat64 returns zero file size.   */
	/* Try to work around it by opening the file and using fstat. */

	ret = -1;

	if (-1 != (fd = _wopen(wpath, O_RDONLY)))
	{
		ret = _fstat64(fd, buf);
		_close(fd);
	}
out:
	zbx_free(wpath);

	return ret;
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: return program name without path                                  *
 *                                                                            *
 * Return value: program name without path                                    *
 *                                                                            *
 ******************************************************************************/
const char	*get_program_name(const char *path)
{
	const char	*filename = NULL;

	for (filename = path; path && *path; path++)
	{
		if ('\\' == *path || '/' == *path)
			filename = path + 1;
	}

	return filename;
}

/******************************************************************************
 *                                                                            *
 * Purpose: allocates nmemb * size bytes of memory and fills it with zeros    *
 *                                                                            *
 * Return value: returns a pointer to the newly allocated memory              *
 *                                                                            *
 ******************************************************************************/
void	*zbx_calloc2(const char *filename, int line, void *old, size_t nmemb, size_t size)
{
	int	max_attempts;
	void	*ptr = NULL;

	/* old pointer must be NULL */
	if (NULL != old)
	{
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_calloc: allocating already allocated memory. "
				"Please report this to Zabbix developers.",
				filename, line);
	}

	for (
		max_attempts = 10, nmemb = MAX(nmemb, 1), size = MAX(size, 1);
		0 < max_attempts && NULL == ptr;
		ptr = calloc(nmemb, size), max_attempts--
	);

	if (NULL != ptr)
		return ptr;

	zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_calloc: out of memory. Requested " ZBX_FS_SIZE_T " bytes.",
			filename, line, (zbx_fs_size_t)size);

	exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Purpose: allocates size bytes of memory                                    *
 *                                                                            *
 * Return value: returns a pointer to the newly allocated memory              *
 *                                                                            *
 ******************************************************************************/
void	*zbx_malloc2(const char *filename, int line, void *old, size_t size)
{
	int	max_attempts;
	void	*ptr = NULL;

	/* old pointer must be NULL */
	if (NULL != old)
	{
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_malloc: allocating already allocated memory. "
				"Please report this to Zabbix developers.",
				filename, line);
	}

	for (
		max_attempts = 10, size = MAX(size, 1);
		0 < max_attempts && NULL == ptr;
		ptr = malloc(size), max_attempts--
	);

	if (NULL != ptr)
		return ptr;

	zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_malloc: out of memory. Requested " ZBX_FS_SIZE_T " bytes.",
			filename, line, (zbx_fs_size_t)size);

	exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Purpose: changes the size of the memory block pointed to by old            *
 *          to size bytes                                                     *
 *                                                                            *
 * Return value: returns a pointer to the newly allocated memory              *
 *                                                                            *
 ******************************************************************************/
void	*zbx_realloc2(const char *filename, int line, void *old, size_t size)
{
	int	max_attempts;
	void	*ptr = NULL;

	for (
		max_attempts = 10, size = MAX(size, 1);
		0 < max_attempts && NULL == ptr;
		ptr = realloc(old, size), max_attempts--
	);

	if (NULL != ptr)
		return ptr;

	zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_realloc: out of memory. Requested " ZBX_FS_SIZE_T " bytes.",
			filename, line, (zbx_fs_size_t)size);

	exit(EXIT_FAILURE);
}

char	*zbx_strdup2(const char *filename, int line, char *old, const char *str)
{
	int	retry;
	char	*ptr = NULL;

	zbx_free(old);

	for (retry = 10; 0 < retry && NULL == ptr; ptr = strdup(str), retry--)
		;

	if (NULL != ptr)
		return ptr;

	zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_strdup: out of memory. Requested " ZBX_FS_SIZE_T " bytes.",
			filename, line, (zbx_fs_size_t)(strlen(str) + 1));

	exit(EXIT_FAILURE);
}

/****************************************************************************************
 *                                                                                      *
 * Purpose: For overwriting sensitive data in memory.                                   *
 *          Similar to memset() but should not be optimized out by a compiler.          *
 *                                                                                      *
 * Derived from:                                                                        *
 *   http://www.dwheeler.com/secure-programs/Secure-Programs-HOWTO/protect-secrets.html *
 * See also:                                                                            *
 *   http://www.open-std.org/jtc1/sc22/wg14/www/docs/n1381.pdf on secure_memset()       *
 *                                                                                      *
 ****************************************************************************************/
void	*zbx_guaranteed_memset(void *v, int c, size_t n)
{
	volatile signed char	*p = (volatile signed char *)v;

	while (0 != n--)
		*p++ = (signed char)c;

	return v;
}

/******************************************************************************
 *                                                                            *
 * Purpose: print application parameters on stdout with layout suitable for   *
 *          80-column terminal                                                *
 *                                                                            *
 * Comments:  usage_message - is global variable which must be initialized    *
 *                            in each zabbix application                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_usage(void)
{
#define ZBX_MAXCOL	79
#define ZBX_SPACE1	"  "			/* left margin for the first line */
#define ZBX_SPACE2	"               "	/* left margin for subsequent lines */
	const char	**p = usage_message;

	if (NULL != *p)
		printf("usage:\n");

	while (NULL != *p)
	{
		size_t	pos;

		printf("%s%s", ZBX_SPACE1, progname);
		pos = ZBX_CONST_STRLEN(ZBX_SPACE1) + strlen(progname);

		while (NULL != *p)
		{
			size_t	len;

			len = strlen(*p);

			if (ZBX_MAXCOL > pos + len)
			{
				pos += len + 1;
				printf(" %s", *p);
			}
			else
			{
				pos = ZBX_CONST_STRLEN(ZBX_SPACE2) + len + 1;
				printf("\n%s %s", ZBX_SPACE2, *p);
			}

			p++;
		}

		printf("\n");
		p++;
	}
#undef ZBX_MAXCOL
#undef ZBX_SPACE1
#undef ZBX_SPACE2
}

static const char	copyright_message[] =
	"Copyright (C) 2022 Zabbix SIA\n"
	"License GPLv2+: GNU GPL version 2 or later <http://gnu.org/licenses/gpl.html>.\n"
	"This is free software: you are free to change and redistribute it according to\n"
	"the license. There is NO WARRANTY, to the extent permitted by law.";

static const char	help_message_footer[] =
	"Report bugs to: <https://support.zabbix.com>\n"
	"Zabbix home page: <http://www.zabbix.com>\n"
	"Documentation: <https://www.zabbix.com/documentation>";

/******************************************************************************
 *                                                                            *
 * Purpose: print help of application parameters on stdout by application     *
 *          request with parameter '-h'                                       *
 *                                                                            *
 * Comments:  help_message - is global variable which must be initialized     *
 *                            in each zabbix application                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_help(void)
{
	const char	**p = help_message;

	zbx_usage();
	printf("\n");

	while (NULL != *p)
		printf("%s\n", *p++);

	printf("\n");
	puts(help_message_footer);
}

/******************************************************************************
 *                                                                            *
 * Purpose: print version and compilation time of application on stdout       *
 *          by application request with parameter '-V'                        *
 *                                                                            *
 * Comments:  title_message - is global variable which must be initialized    *
 *                            in each zabbix application                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_version(void)
{
	printf("%s (Zabbix) %s\n", title_message, ZABBIX_VERSION);
	printf("Revision %s %s, compilation time: %s %s\n\n", ZABBIX_REVISION, ZABBIX_REVDATE, __DATE__, __TIME__);
	puts(copyright_message);
}

/******************************************************************************
 *                                                                            *
 * Purpose: set process title                                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_setproctitle(const char *fmt, ...)
{
#if defined(HAVE_FUNCTION_SETPROCTITLE) || defined(PS_OVERWRITE_ARGV) || defined(PS_PSTAT_ARGV)
	char	title[MAX_STRING_LEN];
	va_list	args;

	va_start(args, fmt);
	zbx_vsnprintf(title, sizeof(title), fmt, args);
	va_end(args);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() title:'%s'", __func__, title);
#endif

#if defined(HAVE_FUNCTION_SETPROCTITLE)
	setproctitle("%s", title);
#elif defined(PS_OVERWRITE_ARGV) || defined(PS_PSTAT_ARGV)
	setproctitle_set_status(title);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if string is a valid internet hostname                      *
 *                                                                            *
 * Parameters: hostname - [IN] hostname string to be checked                  *
 *                                                                            *
 * Return value: SUCCEED - could be a valid hostname,                         *
 *               FAIL - definitely not a valid hostname                       *
 * Comments:                                                                  *
 *     Validation is not strict. Restrictions not checked:                    *
 *         - individual label (component) length 1-63,                        *
 *         - hyphens ('-') allowed only as interior characters in labels,     *
 *         - underscores ('_') allowed in domain name, but not in hostname.   *
 *                                                                            *
 ******************************************************************************/
int	zbx_validate_hostname(const char *hostname)
{
	int		component;	/* periods ('.') are only allowed when they serve to delimit components */
	int		len = ZBX_MAX_DNSNAME_LEN;
	const char	*p;

	/* the first character must be an alphanumeric character */
	if (0 == isalnum(*hostname))
		return FAIL;

	/* check only up to the first 'len' characters, the 1st character is already successfully checked */
	for (p = hostname + 1, component = 1; '\0' != *p; p++)
	{
		if (0 == --len)				/* hostname too long */
			return FAIL;

		/* check for allowed characters */
		if (0 != isalnum(*p) || '-' == *p || '_' == *p)
			component = 1;
		else if ('.' == *p && 1 == component)
			component = 0;
		else
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get nearest index position of sorted elements in array            *
 *                                                                            *
 * Parameters: p   - pointer to array of elements                             *
 *             sz  - element size                                             *
 *             num - number of elements                                       *
 *             id  - index to look for                                        *
 *                                                                            *
 * Return value: index at which it would be possible to insert the element so *
 *               that the array is still sorted                               *
 *                                                                            *
 ******************************************************************************/
int	get_nearestindex(const void *p, size_t sz, int num, zbx_uint64_t id)
{
	int		first_index, last_index, index;
	zbx_uint64_t	element_id;

	if (0 == num)
		return 0;

	first_index = 0;
	last_index = num - 1;

	while (1)
	{
		index = first_index + (last_index - first_index) / 2;

		if (id == (element_id = *(const zbx_uint64_t *)((const char *)p + index * sz)))
			return index;

		if (last_index == first_index)
		{
			if (element_id < id)
				index++;
			return index;
		}

		if (element_id < id)
			first_index = index + 1;
		else
			last_index = index;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: add uint64 value to dynamic array                                 *
 *                                                                            *
 ******************************************************************************/
int	uint64_array_add(zbx_uint64_t **values, int *alloc, int *num, zbx_uint64_t value, int alloc_step)
{
	int	index;

	index = get_nearestindex(*values, sizeof(zbx_uint64_t), *num, value);
	if (index < (*num) && (*values)[index] == value)
		return index;

	if (*alloc == *num)
	{
		if (0 == alloc_step)
		{
			zbx_error("Unable to reallocate buffer");
			assert(0);
		}

		*alloc += alloc_step;
		*values = (zbx_uint64_t *)zbx_realloc(*values, *alloc * sizeof(zbx_uint64_t));
	}

	memmove(&(*values)[index + 1], &(*values)[index], sizeof(zbx_uint64_t) * (*num - index));

	(*values)[index] = value;
	(*num)++;

	return index;
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove uint64 values from array                                   *
 *                                                                            *
 ******************************************************************************/
void	uint64_array_remove(zbx_uint64_t *values, int *num, const zbx_uint64_t *rm_values, int rm_num)
{
	int	rindex, index;

	for (rindex = 0; rindex < rm_num; rindex++)
	{
		index = get_nearestindex(values, sizeof(zbx_uint64_t), *num, rm_values[rindex]);
		if (index == *num || values[index] != rm_values[rindex])
			continue;

		memmove(&values[index], &values[index + 1], sizeof(zbx_uint64_t) * ((*num) - index - 1));
		(*num)--;
	}
}

/******************************************************************************
 *                                                                            *
 * Return value:  SUCCEED - the char is allowed in the host name              *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Comments: in host name allowed characters: '0-9a-zA-Z. _-'                 *
 *           !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	is_hostname_char(unsigned char c)
{
	if (0 != isalnum(c))
		return SUCCEED;

	if (c == '.' || c == ' ' || c == '_' || c == '-')
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Return value:  SUCCEED - the char is allowed in the item key               *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Comments: in key allowed characters: '0-9a-zA-Z._-'                        *
 *           !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	is_key_char(unsigned char c)
{
	if (0 != isalnum(c))
		return SUCCEED;

	if (c == '.' || c == '_' || c == '-')
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Return value:  SUCCEED - the char is allowed in the trigger function       *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Comments: in trigger function allowed characters: 'a-z'                    *
 *           !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	is_function_char(unsigned char c)
{
	if (0 != islower(c))
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Return value:  SUCCEED - the char is allowed in the macro name             *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Comments: allowed characters in macro names: '0-9A-Z._'                    *
 *           !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	is_macro_char(unsigned char c)
{
	if (0 != isupper(c))
		return SUCCEED;

	if ('.' == c || '_' == c)
		return SUCCEED;

	if (0 != isdigit(c))
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the name is a valid discovery macro                     *
 *                                                                            *
 * Return value:  SUCCEED - the name is a valid discovery macro               *
 *                FAIL - otherwise                                            *
 *                                                                            *
 ******************************************************************************/
int	is_discovery_macro(const char *name)
{
	if ('{' != *name++ || '#' != *name++)
		return FAIL;

	do
	{
		if (SUCCEED != is_macro_char(*name++))
			return FAIL;

	} while ('}' != *name);

	if ('\0' != name[1])
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: advances pointer to first invalid character in string             *
 *          ensuring that everything before it is a valid key                 *
 *                                                                            *
 *  e.g., system.run[cat /etc/passwd | awk -F: '{ print $1 }']                *
 *                                                                            *
 * Parameters: exp - [IN/OUT] pointer to the first char of key                *
 *                                                                            *
 *  e.g., {host:system.run[cat /etc/passwd | awk -F: '{ print $1 }'].last(0)} *
 *              ^                                                             *
 * Return value: returns FAIL only if no key is present (length 0),           *
 *               or the whole string is invalid. SUCCEED otherwise.           *
 *                                                                            *
 * Comments: the pointer is advanced to the first invalid character even if   *
 *           FAIL is returned (meaning there is a syntax error in item key).  *
 *           If necessary, the caller must keep a copy of pointer original    *
 *           value.                                                           *
 *                                                                            *
 ******************************************************************************/
int	parse_key(const char **exp)
{
	const char	*s;

	for (s = *exp; SUCCEED == is_key_char(*s); s++)
		;

	if (*exp == s)	/* the key is empty */
		return FAIL;

	if ('[' == *s)	/* for instance, net.tcp.port[,80] */
	{
		int	state = 0;	/* 0 - init, 1 - inside quoted param, 2 - inside unquoted param */
		int	array = 0;	/* array nest level */

		for (s++; '\0' != *s; s++)
		{
			switch (state)
			{
				/* init state */
				case 0:
					if (',' == *s)
						;
					else if ('"' == *s)
						state = 1;
					else if ('[' == *s)
					{
						if (0 == array)
							array = 1;
						else
							goto fail;	/* incorrect syntax: multi-level array */
					}
					else if (']' == *s && 0 != array)
					{
						array = 0;
						s++;

						while (' ' == *s)	/* skip trailing spaces after closing ']' */
							s++;

						if (']' == *s)
							goto succeed;

						if (',' != *s)
							goto fail;	/* incorrect syntax */
					}
					else if (']' == *s && 0 == array)
						goto succeed;
					else if (' ' != *s)
						state = 2;
					break;
				/* quoted */
				case 1:
					if ('"' == *s)
					{
						while (' ' == s[1])	/* skip trailing spaces after closing quotes */
							s++;

						if (0 == array && ']' == s[1])
						{
							s++;
							goto succeed;
						}

						if (',' != s[1] && !(0 != array && ']' == s[1]))
						{
							s++;
							goto fail;	/* incorrect syntax */
						}

						state = 0;
					}
					else if ('\\' == *s && '"' == s[1])
						s++;
					break;
				/* unquoted */
				case 2:
					if (',' == *s || (']' == *s && 0 != array))
					{
						s--;
						state = 0;
					}
					else if (']' == *s && 0 == array)
						goto succeed;
					break;
			}
		}
fail:
		*exp = s;
		return FAIL;
succeed:
		s++;
	}

	*exp = s;
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return hostname and key                                           *
 *          <hostname:>key                                                    *
 *                                                                            *
 * Parameters:                                                                *
 *         exp - pointer to the first char of hostname                        *
 *                host:key[key params]                                        *
 *                ^                                                           *
 *                                                                            *
 * Return value: return SUCCEED or FAIL                                       *
 *                                                                            *
 ******************************************************************************/
int	parse_host_key(char *exp, char **host, char **key)
{
	char	*p, *s;

	if (NULL == exp || '\0' == *exp)
		return FAIL;

	for (p = exp, s = exp; '\0' != *p; p++)	/* check for optional hostname */
	{
		if (':' == *p)	/* hostname:vfs.fs.size[/,total]
				 * --------^
				 */
		{
			*p = '\0';
			*host = zbx_strdup(NULL, s);
			*p++ = ':';

			s = p;
			break;
		}

		if (SUCCEED != is_hostname_char(*p))
			break;
	}

	*key = zbx_strdup(NULL, s);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: replace all not-allowed hostname characters in the string         *
 *                                                                            *
 * Parameters: host - the target C-style string                               *
 *                                                                            *
 * Comments: the string must be null-terminated, otherwise not secure!        *
 *                                                                            *
 ******************************************************************************/
void	make_hostname(char *host)
{
	char	*c;

	assert(host);

	for (c = host; '\0' != *c; ++c)
	{
		if (FAIL == is_hostname_char(*c))
			*c = '_';
	}
}

/******************************************************************************
 *                                                                            *
 * Return value: Interface type                                               *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
unsigned char	get_interface_type_by_item_type(unsigned char type)
{
	switch (type)
	{
		case ITEM_TYPE_ZABBIX:
			return INTERFACE_TYPE_AGENT;
		case ITEM_TYPE_SNMP:
		case ITEM_TYPE_SNMPTRAP:
			return INTERFACE_TYPE_SNMP;
		case ITEM_TYPE_IPMI:
			return INTERFACE_TYPE_IPMI;
		case ITEM_TYPE_JMX:
			return INTERFACE_TYPE_JMX;
		case ITEM_TYPE_SIMPLE:
		case ITEM_TYPE_EXTERNAL:
		case ITEM_TYPE_SSH:
		case ITEM_TYPE_TELNET:
		case ITEM_TYPE_SCRIPT:
			return INTERFACE_TYPE_ANY;
		case ITEM_TYPE_HTTPAGENT:
			return INTERFACE_TYPE_OPT;
		default:
			return INTERFACE_TYPE_UNKNOWN;
	}
}

void	zbx_alarm_flag_set(void)
{
	zbx_timed_out = 1;
}

void	zbx_alarm_flag_clear(void)
{
	zbx_timed_out = 0;
}

#if !defined(_WINDOWS) && !defined(__MINGW32__)
unsigned int	zbx_alarm_on(unsigned int seconds)
{
	zbx_alarm_flag_clear();

	return alarm(seconds);
}

unsigned int	zbx_alarm_off(void)
{
	unsigned int	ret;

	ret = alarm(0);
	zbx_alarm_flag_clear();
	return ret;
}
#endif

int	zbx_alarm_timed_out(void)
{
	return (0 == zbx_timed_out ? FAIL : SUCCEED);
}

#if !defined(_WINDOWS) && defined(HAVE_RESOLV_H)
/******************************************************************************
 *                                                                            *
 * Purpose: react to "/etc/resolv.conf" update                                *
 *                                                                            *
 * Comments: it is intended to call this function in the end of each process  *
 *           main loop. The purpose of calling it at the end (instead of the  *
 *           beginning of main loop) is to let the first initialization of    *
 *           libc resolver proceed internally.                                *
 *                                                                            *
 ******************************************************************************/
static void	update_resolver_conf(void)
{
#define ZBX_RESOLV_CONF_FILE	"/etc/resolv.conf"

	static time_t	mtime = 0;
	zbx_stat_t	buf;

	if (0 == zbx_stat(ZBX_RESOLV_CONF_FILE, &buf) && mtime != buf.st_mtime)
	{
		mtime = buf.st_mtime;

		if (0 != res_init())
			zabbix_log(LOG_LEVEL_WARNING, "update_resolver_conf(): res_init() failed");
	}

#undef ZBX_RESOLV_CONF_FILE
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: throttling of update "/etc/resolv.conf" and "stdio" to the new    *
 *          log file after rotation                                           *
 *                                                                            *
 * Parameters: time_now - [IN] the time for compare in seconds                *
 *                                                                            *
 ******************************************************************************/
void	zbx_update_env(double time_now)
{
	static double	time_update = 0;

	/* handle /etc/resolv.conf update and log rotate less often than once a second */
	if (1.0 < time_now - time_update)
	{
		time_update = time_now;
		zbx_handle_log();
#if !defined(_WINDOWS) && defined(HAVE_RESOLV_H)
		update_resolver_conf();
#endif
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Print error text to the stderr                                    *
 *                                                                            *
 * Parameters: fmt - format of message                                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_error(const char *fmt, ...)
{
	va_list	args;

	va_start(args, fmt);

	fprintf(stderr, "%s [%li]: ", progname, zbx_get_thread_id());
	vfprintf(stderr, fmt, args);
	fprintf(stderr, "\n");
	fflush(stderr);

	va_end(args);
}

zbx_uint64_t	suffix2factor(char c)
{
	switch (c)
	{
		case 'K':
			return ZBX_KIBIBYTE;
		case 'M':
			return ZBX_MEBIBYTE;
		case 'G':
			return ZBX_GIBIBYTE;
		case 'T':
			return ZBX_TEBIBYTE;
		case 's':
			return 1;
		case 'm':
			return SEC_PER_MIN;
		case 'h':
			return SEC_PER_HOUR;
		case 'd':
			return SEC_PER_DAY;
		case 'w':
			return SEC_PER_WEEK;
		default:
			return 1;
	}
}

void	zbx_free_tag(zbx_tag_t *tag)
{
	zbx_free(tag->tag);
	zbx_free(tag->value);
	zbx_free(tag);
}
