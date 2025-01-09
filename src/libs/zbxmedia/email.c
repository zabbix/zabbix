/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxmedia.h"

#include "zbxstr.h"
#include "zbxcomms.h"
#include "zbxcrypto.h"
#include "zbxalgo.h"

#ifdef HAVE_LIBCURL
#	include "zbxcurl.h"
#endif

/* number of characters per line when wrapping Base64 data in Email */
#define ZBX_EMAIL_B64_MAXLINE			76

/* number of characters per "encoded-word" in RFC-2047 message header */
#define ZBX_EMAIL_B64_MAXWORD_RFC2047		75

/* multiple 'encoded-word's should be separated by <CR><LF><SPACE> */
#define ZBX_EMAIL_ENCODED_WORD_SEPARATOR	"\r\n "

/* separator for multipart mixed messages */
#define ZBX_MULTIPART_MIXED_BOUNDARY	"MULTIPART-MIXED-BOUNDARY"

#define OK_250	"250"

/******************************************************************************
 *                                                                            *
 * Purpose: Encode a string into a base64 string as required by rfc2047.      *
 *          Used for encoding e-mail headers.                                 *
 *                                                                            *
 * Parameters: src      - [IN] a null-terminated UTF-8 string to encode       *
 *             p_base64 - [OUT] a pointer to the encoded string               *
 *                                                                            *
 * Comments: Based on the patch submitted by                                  *
 *           Jairo Eduardo Lopez Fuentes Nacarino                             *
 *                                                                            *
 ******************************************************************************/
static void	str_base64_encode_rfc2047(const char *src, char **p_base64)
{
	const char	*p0;			/* pointer in src to start encoding from */
	const char	*p1;			/* pointer in src: 1st byte of UTF-8 character */
	size_t		c_len;			/* length of UTF-8 character sequence */
	size_t		p_base64_alloc;		/* allocated memory size for subject */
	size_t		p_base64_offset = 0;	/* offset for writing into subject */

	assert(src);
	assert(NULL == *p_base64);		/* do not accept already allocated memory */

	p_base64_alloc = ZBX_EMAIL_B64_MAXWORD_RFC2047 + sizeof(ZBX_EMAIL_ENCODED_WORD_SEPARATOR);
	*p_base64 = (char *)zbx_malloc(NULL, p_base64_alloc);
	**p_base64 = '\0';

	for (p0 = src; '\0' != *p0; p0 = p1)
	{
		/* Max length of line is 76 characters (without line separator). */
		/* Max length of "encoded-word" is 75 characters (without word separator). */
		/* 3 characters are taken by word separator "<CR><LF><Space>" which also includes the line separator. */
		/* 12 characters are taken by header "=?UTF-8?B?" and trailer "?=". */
		/* So, one "encoded-word" can hold up to 63 characters of Base64-encoded string. */
		/* Encoding 45 bytes produces a 61 byte long Base64-encoded string which meets the limit. */
		/* Encoding 46 bytes produces a 65 byte long Base64-encoded string which exceeds the limit. */
		for (p1 = p0; '\0' != *p1; p1 += c_len)
		{
			/* an invalid UTF-8 character or length of a string more than 45 bytes */
			if (0 == (c_len = zbx_utf8_char_len(p1)) || 45 < p1 - p0 + c_len)
				break;
		}

		if (0 < p1 - p0)
		{
			/* 12 characters are taken by header "=?UTF-8?B?" and trailer "?=" plus '\0' */
			char	b64_buf[ZBX_EMAIL_B64_MAXWORD_RFC2047 - 12 + 1];

			zbx_base64_encode(p0, b64_buf, p1 - p0);

			if (0 != p_base64_offset)	/* not the first "encoded-word" ? */
			{
				zbx_strcpy_alloc(p_base64, &p_base64_alloc, &p_base64_offset,
						ZBX_EMAIL_ENCODED_WORD_SEPARATOR);
			}

			zbx_snprintf_alloc(p_base64, &p_base64_alloc, &p_base64_offset, "=?UTF-8?B?%s?=", b64_buf);
		}
		else
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Comments: reads until '\n'                                                 *
 *                                                                            *
 ******************************************************************************/
static int	smtp_readln(zbx_socket_t *s, const char **buf)
{
	while (NULL != (*buf = zbx_tcp_recv_line(s)) &&
			4 <= strlen(*buf) &&
			0 != isdigit((*buf)[0]) &&
			0 != isdigit((*buf)[1]) &&
			0 != isdigit((*buf)[2]) &&
			'-' == (*buf)[3])
		;

	return NULL == *buf ? FAIL : SUCCEED;
}

/********************************************************************************
 *                                                                              *
 * Purpose: 1. Extract a display name and an angle address from mailbox string  *
 *             for using in "MAIL FROM:", "RCPT TO:", "From:" and "To:" fields. *
 *          2. If the display name contains multibyte UTF-8 characters encode   *
 *             it into a base64 string as required by rfc2047. The encoding is  *
 *             also applied if the display name looks like a base64-encoded     *
 *             word.                                                            *
 *                                                                              *
 * Parameters: mailbox       - [IN] a null-terminated UTF-8 string              *
 *             error         - [IN] pointer to string for reporting errors      *
 *             mailaddrs     - [OUT] array of mail addresses                    *
 *                                                                              *
 * Comments:   The function is very much simplified in comparison with full     *
 *             RFC 5322-compliant parser. It does not recognize:                *
 *                - comments,                                                   *
 *                - quoted strings and quoted pairs,                            *
 *                - folding whitespace.                                         *
 *             For example, '<' and '@' are not supported in the display name   *
 *             and the local part of email address.                             *
 *                                                                              *
 ********************************************************************************/
static int	smtp_parse_mailbox(const char *mailbox, char **error, zbx_vector_ptr_t *mailaddrs)
{
	const char	*p, *pstart, *angle_addr_start, *domain_start, *utf8_end;
	const char	*base64_like_start, *base64_like_end, *token;
	char		*base64_buf, *tmp_mailbox;
	size_t		size_angle_addr = 0, offset_angle_addr = 0, len, i;
	int		ret = FAIL;
	zbx_mailaddr_t	*mailaddr = NULL;

	tmp_mailbox = zbx_strdup(NULL, mailbox);

	token = strtok(tmp_mailbox, "\n");
	while (token != NULL)
	{
		angle_addr_start = NULL;
		domain_start = NULL;
		utf8_end = NULL;
		base64_like_start = NULL;
		base64_like_end = NULL;
		base64_buf = NULL;

		p = token;

		while (' ' == *p || '\t' == *p)
			p++;

		pstart = p;

		while ('\0' != *p)
		{
			len = zbx_utf8_char_len(p);

			if (1 == len)	/* ASCII character */
			{
				switch (*p)
				{
					case '<':
						angle_addr_start = p;
						break;
					case '@':
						domain_start = p;
						break;
					/* if mailbox contains a sequence '=?'.*'?=' which looks like a Base64-encoded word */
					case '=':
						if ('?' == *(p + 1))
							base64_like_start = p++;
						break;
					case '?':
						if (NULL != base64_like_start && '=' == *(p + 1))
							base64_like_end = p++;
				}
				p++;
			}
			else if (1 < len)	/* multibyte UTF-8 character */
			{
				for (i = 1; i < len; i++)
				{
					if ('\0' == *(p + i))
					{
						*error = zbx_dsprintf(*error, "invalid UTF-8 character in email"
								" address: %s", token);
						goto out;
					}
				}
				utf8_end = p + len - 1;
				p += len;
			}
			else if (0 == len)	/* invalid UTF-8 character */
			{
				*error = zbx_dsprintf(*error, "invalid UTF-8 character in email address: %s", token);
				goto out;
			}
		}

		if (NULL == domain_start)
		{
			*error = zbx_dsprintf(*error, "no '@' in email address: %s", token);
			goto out;
		}

		if (utf8_end > angle_addr_start)
		{
			*error = zbx_dsprintf(*error, "email address local or domain part contains UTF-8 character: %s",
					token);
			goto out;
		}

		mailaddr = (zbx_mailaddr_t *)zbx_malloc(NULL, sizeof(zbx_mailaddr_t));
		memset(mailaddr, 0, sizeof(zbx_mailaddr_t));

		if (NULL != angle_addr_start)
		{
			zbx_snprintf_alloc(&mailaddr->addr, &size_angle_addr, &offset_angle_addr, "%s",
					angle_addr_start);

			if (pstart < angle_addr_start)	/* display name */
			{
				mailaddr->disp_name = (char *)zbx_malloc(mailaddr->disp_name,
						(size_t)(angle_addr_start - pstart + 1));
				memcpy(mailaddr->disp_name, pstart, (size_t)(angle_addr_start - pstart));
				*(mailaddr->disp_name + (angle_addr_start - pstart)) = '\0';

				/* UTF-8 or Base64-looking display name */
				if (NULL != utf8_end || (NULL != base64_like_end &&
						angle_addr_start - 1 > base64_like_end))
				{
					str_base64_encode_rfc2047(mailaddr->disp_name, &base64_buf);
					zbx_free(mailaddr->disp_name);
					mailaddr->disp_name = base64_buf;
				}
			}
		}
		else
		{
			zbx_snprintf_alloc(&mailaddr->addr, &size_angle_addr, &offset_angle_addr, "<%s>", pstart);
		}

		zbx_vector_ptr_append(mailaddrs, mailaddr);

		token = strtok(NULL, "\n");
	}

	ret = SUCCEED;
out:
	zbx_free(tmp_mailbox);

	return ret;
}

static char	*email_encode_part(const char *data, size_t data_size)
{
	char	*base64 = NULL, *part;

	zbx_base64_encode_dyn(data, &base64, data_size);
	part = zbx_str_linefeed(base64, ZBX_EMAIL_B64_MAXLINE, "\r\n");
	zbx_free(base64);

	return part;
}

static char	*smtp_prepare_payload(zbx_vector_ptr_t *from_mails, zbx_vector_ptr_t *to_mails, const char *inreplyto,
		const char *mailsubject, const char *mailbody, unsigned char message_format)
{
	char		*tmp = NULL, *base64 = NULL;
	char		*localsubject = NULL, *localbody = NULL, *from = NULL, *to = NULL;
	char		str_time[MAX_STRING_LEN];
	struct tm	*local_time;
	time_t		email_time;
	int		i;
	size_t		from_alloc = 0, from_offset = 0, to_alloc = 0, to_offset = 0, tmp_alloc = 0, tmp_offset = 0;

	/* prepare subject */

	tmp = zbx_string_replace(mailsubject, "\r\n", " ");
	localsubject = zbx_string_replace(tmp, "\n", " ");
	zbx_free(tmp);

	if (FAIL == zbx_is_ascii_string(localsubject))
	{
		/* split subject into multiple RFC 2047 "encoded-words" */
		str_base64_encode_rfc2047(localsubject, &base64);
		zbx_free(localsubject);

		localsubject = base64;
		base64 = NULL;
	}

	/* prepare body */

	if (ZBX_MEDIA_MESSAGE_FORMAT_MULTI != message_format)
	{
		char	*tmp_body;

		tmp = zbx_string_replace(mailbody, "\r\n", "\n");
		tmp_body = zbx_string_replace(tmp, "\n", "\r\n");
		localbody = email_encode_part(tmp_body, strlen(tmp_body));
		zbx_free(tmp_body);
		zbx_free(tmp);
	}
	else
		localbody = (char *)mailbody;

	/* prepare date */

	time(&email_time);
	local_time = localtime(&email_time);
	strftime(str_time, MAX_STRING_LEN, "%a, %d %b %Y %H:%M:%S %z", local_time);

	for (i = 0; i < from_mails->values_num; i++)
	{
		zbx_snprintf_alloc(&from, &from_alloc, &from_offset, "%s%s",
				ZBX_NULL2EMPTY_STR(((zbx_mailaddr_t *)from_mails->values[i])->disp_name),
				((zbx_mailaddr_t *)from_mails->values[i])->addr);

		if (from_mails->values_num - 1 > i)
			zbx_strcpy_alloc(&from, &from_alloc, &from_offset, ",");
	}

	for (i = 0; i < to_mails->values_num; i++)
	{
		zbx_snprintf_alloc(&to, &to_alloc, &to_offset, "%s%s",
				ZBX_NULL2EMPTY_STR(((zbx_mailaddr_t *)to_mails->values[i])->disp_name),
				((zbx_mailaddr_t *)to_mails->values[i])->addr);

		if (to_mails->values_num - 1 > i)
			zbx_strcpy_alloc(&to, &to_alloc, &to_offset, ",");
	}

	/* e-mails are sent in 'SMTP/MIME e-mail' format because UTF-8 is used both in mailsubject and mailbody */
	/* =?charset?encoding?encoded text?= format must be used for subject field */

	zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset,
			"From: %s\r\n"
			"To: %s\r\n"
			"In-Reply-To: %s\r\n"
			"Date: %s\r\n"
			"Subject: %s\r\n"
			"MIME-Version: 1.0\r\n",
			from, to, inreplyto, str_time, localsubject);

	if (ZBX_MEDIA_MESSAGE_FORMAT_MULTI == message_format)
	{
		zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset,
				"Content-Type: multipart/mixed; boundary=" ZBX_MULTIPART_MIXED_BOUNDARY "\r\n");
	}
	else
	{
		zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset,
				"Content-Type: %s; charset=\"UTF-8\"\r\n"
				"Content-Transfer-Encoding: base64\r\n",
				ZBX_MEDIA_MESSAGE_FORMAT_HTML == message_format ? "text/html" : "text/plain");
	}

	zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset,
			"\r\n"
			"%s",
			localbody);

	zbx_free(localsubject);
	if (localbody != mailbody)
		zbx_free(localbody);
	zbx_free(from);
	zbx_free(to);

	return tmp;
}

#ifdef HAVE_LIBCURL
typedef struct
{
	char	*payload;
	size_t	payload_len;
	size_t	provided_len;
}
smtp_payload_status_t;

static size_t	smtp_provide_payload(void *buffer, size_t size, size_t nmemb, void *instream)
{
	size_t			current_len;
	smtp_payload_status_t	*payload_status = (smtp_payload_status_t *)instream;

	current_len = MIN(size * nmemb, payload_status->payload_len - payload_status->provided_len);

	memcpy(buffer, payload_status->payload + payload_status->provided_len, current_len);

	payload_status->provided_len += current_len;

	return current_len;
}

static int	smtp_debug_function(CURL *easyhandle, curl_infotype type, char *data, size_t size, void *userptr)
{
	const char	labels[3] = {'*', '<', '>'};

	ZBX_UNUSED(easyhandle);
	ZBX_UNUSED(userptr);

	if (CURLINFO_TEXT != type && CURLINFO_HEADER_IN != type && CURLINFO_HEADER_OUT != type)
		goto out;

	while (0 < size && ('\r' == data[size - 1] || '\n' == data[size - 1]))
		size--;

	zabbix_log(LOG_LEVEL_TRACE, "%c %.*s", labels[type], (int)size, data);
out:
	return 0;
}
#endif

static char	*smtp_get_helo_from_system(void)
{
	struct utsname	name;

	if (-1 == uname(&name))
		return NULL;

	return zbx_strdup(NULL, name.nodename);
}

static char	*smtp_get_helo_from_addr(const char *addr)
{
	const char	*domain;
	char		*helo_addr;
	size_t		addr_len;

	if (NULL == addr || '\0' == *addr || NULL == (domain = strrchr(addr, '@')))
		return NULL;

	addr_len = strlen(domain + 1);

	if (1 == addr_len && '>' == *(domain + 1))
		return NULL;

	helo_addr = zbx_strdup(NULL, domain + 1);
	helo_addr[addr_len - 1] = '\0';

	return helo_addr;
}

static int	send_smtp_helo_plain(const char *addr, const char *helo, zbx_socket_t *s, char **error)
{
	char		cmd[MAX_STRING_LEN], *helo_parsed = NULL;
	const char	*response;
	int			ret = SUCCEED;

	if ('\0' != *helo)
	{
		zbx_snprintf(cmd, sizeof(cmd), "HELO %s\r\n", helo);
	}
	else
	{
		if (NULL == (helo_parsed = smtp_get_helo_from_addr(addr)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() HELO is not specified and failed to parse HELO from email "
					"address, trying to form HELO command using system's hostname", __func__);

			if (NULL == (helo_parsed = smtp_get_helo_from_system()))
			{
				*error = zbx_strdup(*error, "failed to retrieve domain name for HELO command");
				ret = FAIL;
				goto out;
			}
		}

		zbx_snprintf(cmd, sizeof(cmd), "HELO %s\r\n", helo_parsed);
	}

	if (-1 == zbx_tcp_send_raw(s, cmd))
	{
		*error = zbx_dsprintf(*error, "error sending HELO to mailserver: %s", zbx_strerror(errno));
		ret = FAIL;
		goto out;
	}

	if (FAIL == smtp_readln(s, &response))
	{
		*error = zbx_dsprintf(*error, "error receiving answer on HELO request: %s", zbx_strerror(errno));
		ret = FAIL;
		goto out;
	}

	if (0 != strncmp(response, OK_250, ZBX_CONST_STRLEN(OK_250)))
	{
		*error = zbx_dsprintf(*error, "wrong answer on HELO \"%s\"", response);
		ret = FAIL;
		goto out;
	}
out:
	zbx_free(helo_parsed);

	return ret;
}

static const char	*socket_error(zbx_socket_t *s, int socket_errno)
{
	if (SUCCEED != zbx_socket_check_deadline(s))
		return "timeout error";

	return zbx_strerror(socket_errno);
}

static int	send_email_plain(const char *smtp_server, unsigned short smtp_port, const char *smtp_helo,
		zbx_vector_ptr_t *from_mails, zbx_vector_ptr_t *to_mails, const char *inreplyto,
		const char *mailsubject, const char *mailbody, unsigned char message_format, int timeout,
		const char *config_source_ip, char **error)
{
#define OK_220	"220"
#define OK_251	"251"
#define OK_354	"354"
	zbx_socket_t	s;
	int		err, ret = FAIL, i;
	char		cmd[MAX_STRING_LEN], *cmdp = NULL, *helo_addr = NULL;

	const char	*response;

	/* connect to and receive an initial greeting from SMTP server */
	if (FAIL == zbx_tcp_connect(&s, config_source_ip, smtp_server, smtp_port, timeout, ZBX_TCP_SEC_UNENCRYPTED,
			NULL, NULL))
	{
		*error = zbx_dsprintf(*error, "cannot connect to SMTP server \"%s\": %s",
				smtp_server, zbx_socket_strerror());
		goto out;
	}

	if (FAIL == smtp_readln(&s, &response))
	{
		*error = zbx_dsprintf(*error, "error receiving initial string from SMTP server: %s",
				socket_error(&s, errno));
		goto close;
	}

	if (0 != strncmp(response, OK_220, ZBX_CONST_STRLEN(OK_220)))
	{
		*error = zbx_dsprintf(*error, "no welcome message 220* from SMTP server \"%s\"", response);
		goto close;
	}

	/* send HELO */
	if (0 != from_mails->values_num)
		helo_addr = ((zbx_mailaddr_t *)from_mails->values[0])->addr;

	if (FAIL == send_smtp_helo_plain(helo_addr, smtp_helo, &s, error))
		goto close;

	/* send MAIL FROM */

	for (i = 0; i < from_mails->values_num; i++)
	{
		zbx_snprintf(cmd, sizeof(cmd), "MAIL FROM:%s\r\n", ((zbx_mailaddr_t *)from_mails->values[i])->addr);

		if (-1 == zbx_tcp_send_raw(&s, cmd))
		{
			*error = zbx_dsprintf(*error, "error sending MAIL FROM to mailserver: %s",
					socket_error(&s, errno));
			goto close;
		}

		if (FAIL == smtp_readln(&s, &response))
		{
			*error = zbx_dsprintf(*error, "error receiving answer on MAIL FROM request: %s",
					socket_error(&s, errno));
			goto close;
		}

		if (0 != strncmp(response, OK_250, ZBX_CONST_STRLEN(OK_250)))
		{
			*error = zbx_dsprintf(*error, "wrong answer on MAIL FROM \"%s\"", response);
			goto close;
		}
	}

	/* send RCPT TO */

	for (i = 0; i < to_mails->values_num; i++)
	{
		zbx_snprintf(cmd, sizeof(cmd), "RCPT TO:%s\r\n", ((zbx_mailaddr_t *)to_mails->values[i])->addr);

		if (-1 == zbx_tcp_send_raw(&s, cmd))
		{
			*error = zbx_dsprintf(*error, "error sending RCPT TO to mailserver: %s",
					socket_error(&s, errno));
			goto close;
		}

		if (FAIL == smtp_readln(&s, &response))
		{
			*error = zbx_dsprintf(*error, "error receiving answer on RCPT TO request: %s",
					socket_error(&s, errno));
			goto close;
		}

		/* May return 251 as well: User not local; will forward to <forward-path>. See RFC825. */
		if (0 != strncmp(response, OK_250, ZBX_CONST_STRLEN(OK_250)) &&
				0 != strncmp(response, OK_251, ZBX_CONST_STRLEN(OK_251)))
		{
			*error = zbx_dsprintf(*error, "wrong answer on RCPT TO \"%s\"", response);
			goto close;
		}
	}

	/* send DATA */

	zbx_snprintf(cmd, sizeof(cmd), "DATA\r\n");

	if (-1 == zbx_tcp_send_raw(&s, cmd))
	{
		*error = zbx_dsprintf(*error, "error sending DATA to mailserver: %s",
				socket_error(&s, errno));
		goto close;
	}

	if (FAIL == smtp_readln(&s, &response))
	{
		*error = zbx_dsprintf(*error, "error receiving answer on DATA request: %s",
				socket_error(&s, errno));
		goto close;
	}

	if (0 != strncmp(response, OK_354, ZBX_CONST_STRLEN(OK_354)))
	{
		*error = zbx_dsprintf(*error, "wrong answer on DATA \"%s\"", response);
		goto close;
	}

	cmdp = smtp_prepare_payload(from_mails, to_mails, inreplyto, mailsubject, mailbody, message_format);
	err = zbx_tcp_send_raw(&s, cmdp);
	zbx_free(cmdp);

	if (-1 == err)
	{
		*error = zbx_dsprintf(*error, "error sending headers and mail body to mailserver: %s",
				socket_error(&s, errno));
		goto close;
	}

	/* send . */

	zbx_snprintf(cmd, sizeof(cmd), "\r\n.\r\n");

	if (-1 == zbx_tcp_send_raw(&s, cmd))
	{
		*error = zbx_dsprintf(*error, "error sending . to mailserver: %s",
				socket_error(&s, errno));
		goto close;
	}

	if (FAIL == smtp_readln(&s, &response))
	{
		*error = zbx_dsprintf(*error, "error receiving answer on . request: %s",
				socket_error(&s, errno));
		goto close;
	}

	if (0 != strncmp(response, OK_250, ZBX_CONST_STRLEN(OK_250)))
	{
		*error = zbx_dsprintf(*error, "wrong answer on end of data \"%s\"", response);
		goto close;
	}

	/* send QUIT */

	zbx_snprintf(cmd, sizeof(cmd), "QUIT\r\n");

	if (-1 == zbx_tcp_send_raw(&s, cmd))
	{
		*error = zbx_dsprintf(*error, "error sending QUIT to mailserver: %s",
				socket_error(&s, errno));
		goto close;
	}

	ret = SUCCEED;
close:
	zbx_tcp_close(&s);
out:
	return ret;
#undef OK_220
#undef OK_251
#undef OK_354
}

/* SMTP security options */
#define SMTP_SECURITY_NONE	0
#define SMTP_SECURITY_STARTTLS	1
#define SMTP_SECURITY_SSL	2

static int	send_email_curl(const char *smtp_server, unsigned short smtp_port, const char *smtp_helo,
		zbx_vector_ptr_t *from_mails, zbx_vector_ptr_t *to_mails, const char *inreplyto,
		const char *mailsubject, const char *mailbody, unsigned char smtp_security, unsigned char
		smtp_verify_peer, unsigned char smtp_verify_host, unsigned char smtp_authentication,
		const char *username, const char *password, unsigned char message_format, int timeout,
		const char *config_source_ip, const char *config_ssl_ca_location, char **error)
{
#ifdef HAVE_LIBCURL
	int			ret = FAIL, i;
	CURL			*easyhandle;
	CURLcode		err;
	char			url[MAX_STRING_LEN], errbuf[CURL_ERROR_SIZE] = "";
	size_t			url_offset= 0;
	struct curl_slist	*recipients = NULL;
	smtp_payload_status_t	payload_status;

	if (SMTP_SECURITY_NONE != smtp_security && SUCCEED != zbx_curl_has_ssl(error))
		goto out;

	if (SMTP_AUTHENTICATION_NONE != smtp_authentication && SUCCEED != zbx_curl_has_smtp_auth(error))
		goto out;

	if (NULL == (easyhandle = curl_easy_init()))
	{
		*error = zbx_strdup(*error, "cannot initialize cURL library");
		goto out;
	}

	memset(&payload_status, 0, sizeof(payload_status));

	if (SMTP_SECURITY_SSL == smtp_security)
	{
		if (SUCCEED != zbx_curl_protocol("smtps", error))
			goto clean;

		url_offset += zbx_snprintf(url + url_offset, sizeof(url) - url_offset, "smtps://");
	}
	else
		url_offset += zbx_snprintf(url + url_offset, sizeof(url) - url_offset, "smtp://");

	url_offset += zbx_snprintf(url + url_offset, sizeof(url) - url_offset, "%s:%hu", smtp_server, smtp_port);

	if ('\0' != *smtp_helo)
	{
		zbx_snprintf(url + url_offset, sizeof(url) - url_offset, "/%s", smtp_helo);
	}
	else
	{
		char	*helo_domain = NULL;

		if (0 != from_mails->values_num)
		{
			if (NULL == (helo_domain =
					smtp_get_helo_from_addr(((zbx_mailaddr_t *)from_mails->values[0])->addr)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() HELO is not specified and failed to parse HELO "
						"from email address, trying to form HELO command using system's "
						"hostname", __func__);
			}
		}

		if (NULL == helo_domain)
		{
			if (NULL == (helo_domain = smtp_get_helo_from_system()))
			{
				*error = zbx_strdup(*error, "failed to retrieve domain name for HELO command");
				goto clean;
			}
		}

		zbx_snprintf(url + url_offset, sizeof(url) - url_offset, "/%s", helo_domain);
		zbx_free(helo_domain);
	}

	if (SUCCEED != zbx_curl_setopt_smtps(easyhandle, error))
		goto clean;

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_URL, url)))
		goto error;

	if (SMTP_SECURITY_NONE != smtp_security)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYPEER,
						0 == smtp_verify_peer ? 0L : 1L)) ||
				CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYHOST,
						0 == smtp_verify_host ? 0L : 2L)))
		{
			goto error;
		}

		if (0 != smtp_verify_peer && NULL != config_ssl_ca_location)
		{
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_CAPATH, config_ssl_ca_location)))
				goto error;
		}

		if (SMTP_SECURITY_STARTTLS == smtp_security)
		{
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_USE_SSL, (long)CURLUSESSL_ALL)))
				goto error;
		}
	}

	if (SMTP_AUTHENTICATION_NORMAL_PASSWORD == smtp_authentication)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_USERNAME, username)) ||
				CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PASSWORD, password)))
		{
			goto error;
		}

		/* Don't specify preferred authentication mechanism implying AUTH=* and let libcurl choose the best */
		/* one (in its mind) among supported by SMTP server. If someday we decide to let user choose their  */
		/* preferred authentication mechanism one should know that:                                         */
		/*   - versions 7.20.0 to 7.30.0 do not support specifying login options                            */
		/*   - versions 7.31.0 to 7.33.0 support login options in CURLOPT_USERPWD                           */
		/*   - versions 7.34.0 and above support explicit CURLOPT_LOGIN_OPTIONS                             */
	}

	if (0 >= from_mails->values_num)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() sender's address is not specified", __func__);
	}
	else if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_MAIL_FROM,
			((zbx_mailaddr_t *)from_mails->values[0])->addr)))
	{
		goto error;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_ACCEPT_ENCODING, "")))
		goto error;

	for (i = 0; i < to_mails->values_num; i++)
		recipients = curl_slist_append(recipients, ((zbx_mailaddr_t *)to_mails->values[i])->addr);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_MAIL_RCPT, recipients)))
		goto error;

	payload_status.payload = smtp_prepare_payload(from_mails, to_mails, inreplyto, mailsubject, mailbody,
			message_format);
	payload_status.payload_len = strlen(payload_status.payload);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_UPLOAD, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_READFUNCTION, smtp_provide_payload)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_READDATA, &payload_status)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_TIMEOUT, (long)timeout)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_ERRORBUFFER, errbuf)))
	{
		goto error;
	}

	if (NULL != config_source_ip)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_INTERFACE, config_source_ip)))
			goto error;
	}

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_VERBOSE, 1L)))
			goto error;

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_DEBUGFUNCTION, smtp_debug_function)))
			goto error;
	}

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_dsprintf(*error, "%s%s%s", curl_easy_strerror(err), ('\0' != *errbuf ? ": " : ""),
				errbuf);
		goto clean;
	}

	ret = SUCCEED;
	goto clean;
error:
	*error = zbx_strdup(*error, curl_easy_strerror(err));
clean:
	zbx_free(payload_status.payload);

	curl_slist_free_all(recipients);
	curl_easy_cleanup(easyhandle);
out:
	return ret;
#else
	ZBX_UNUSED(smtp_server);
	ZBX_UNUSED(smtp_port);
	ZBX_UNUSED(smtp_helo);
	ZBX_UNUSED(from_mails);
	ZBX_UNUSED(to_mails);
	ZBX_UNUSED(inreplyto);
	ZBX_UNUSED(mailsubject);
	ZBX_UNUSED(mailbody);
	ZBX_UNUSED(smtp_security);
	ZBX_UNUSED(smtp_verify_peer);
	ZBX_UNUSED(smtp_verify_host);
	ZBX_UNUSED(smtp_authentication);
	ZBX_UNUSED(username);
	ZBX_UNUSED(password);
	ZBX_UNUSED(message_format);
	ZBX_UNUSED(timeout);
	ZBX_UNUSED(config_source_ip);
	ZBX_UNUSED(config_ssl_ca_location);

	*error = zbx_strdup(*error, "Zabbix server was compiled without cURL library required for SMTP authentication");

	return FAIL;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees the mail address object                                     *
 *                                                                            *
 * Parameters: mailaddr - [IN] the mail address                               *
 *                                                                            *
 ******************************************************************************/
static void	zbx_mailaddr_free(zbx_mailaddr_t *mailaddr)
{
	zbx_free(mailaddr->addr);
	zbx_free(mailaddr->disp_name);
	zbx_free(mailaddr);
}

int	send_email(const char *smtp_server, unsigned short smtp_port, const char *smtp_helo, const char *smtp_email,
		const char *mailto, const char *inreplyto, const char *mailsubject, const char *mailbody,
		unsigned char smtp_security, unsigned char smtp_verify_peer, unsigned char smtp_verify_host,
		unsigned char smtp_authentication, const char *username, const char *password,
		unsigned char message_format, int timeout, const char *config_source_ip,
		const char *config_ssl_ca_location, char **error)
{
	int			ret = FAIL;
	zbx_vector_ptr_t	from_mails, to_mails;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() smtp_server:'%s' smtp_port:%hu smtp_security:%d smtp_authentication:%d",
			__func__, smtp_server, smtp_port, (int)smtp_security, (int)smtp_authentication);

	zbx_vector_ptr_create(&from_mails);
	zbx_vector_ptr_create(&to_mails);

	/* validate addresses before connecting to the server */
	if (SUCCEED != smtp_parse_mailbox(smtp_email, error, &from_mails))
		goto clean;

	if (SUCCEED != smtp_parse_mailbox(mailto, error, &to_mails))
		goto clean;

	/* choose appropriate method for sending the email */
	if (SMTP_SECURITY_NONE == smtp_security && SMTP_AUTHENTICATION_NONE == smtp_authentication)
	{
		ret = send_email_plain(smtp_server, smtp_port, smtp_helo, &from_mails, &to_mails, inreplyto,
				mailsubject, mailbody, message_format, timeout, config_source_ip, error);
	}
	else
	{
		ret = send_email_curl(smtp_server, smtp_port, smtp_helo, &from_mails, &to_mails, inreplyto, mailsubject,
				mailbody, smtp_security, smtp_verify_peer, smtp_verify_host, smtp_authentication,
				username, password, message_format, timeout, config_source_ip, config_ssl_ca_location,
				error);
	}

clean:
	zbx_vector_ptr_clear_ext(&from_mails, (zbx_clean_func_t)zbx_mailaddr_free);
	zbx_vector_ptr_destroy(&from_mails);

	zbx_vector_ptr_clear_ext(&to_mails, (zbx_clean_func_t)zbx_mailaddr_free);
	zbx_vector_ptr_destroy(&to_mails);

	if (FAIL == ret)
		zabbix_log(LOG_LEVEL_WARNING, "failed to send email: %s", *error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

#undef SMTP_SECURITY_NONE
#undef SMTP_SECURITY_STARTTLS
#undef SMTP_SECURITY_SSL

char	*zbx_email_make_body(const char *message, unsigned char message_format,  const char *attachment_name,
		const char *attachment_type, const char *attachment, size_t attachment_size)
{
	size_t	body_alloc = 0, body_offset = 0;
	char	*body = NULL, *localbody, *tmp, *tmp_body, *localattachment;

	tmp = zbx_string_replace(message, "\r\n", "\n");
	tmp_body = zbx_string_replace(tmp, "\n", "\r\n");
	localbody = email_encode_part(tmp_body, strlen(tmp_body));
	zbx_free(tmp_body);
	zbx_free(tmp);

	zbx_snprintf_alloc(&body, &body_alloc, &body_offset,
			"--" ZBX_MULTIPART_MIXED_BOUNDARY "\r\n"
			"Content-Type: %s; charset=\"UTF-8\"\r\n"
			"Content-Transfer-Encoding: base64\r\n"
			"\r\n"
			"%s\r\n"
			"\r\n",
			ZBX_MEDIA_MESSAGE_FORMAT_HTML == message_format ? "text/html" : "text/plain",
			localbody);

	zbx_free(localbody);

	localattachment = email_encode_part(attachment, attachment_size);

	zbx_snprintf_alloc(&body, &body_alloc, &body_offset,
			"--" ZBX_MULTIPART_MIXED_BOUNDARY "\r\n"
			"Content-Type: %s\r\n"
			"Content-Transfer-Encoding: base64\r\n"
			"Content-Disposition: attachment; filename=\"%s\"\r\n"
			"\r\n"
			"%s\r\n"
			"\r\n"
			"--" ZBX_MULTIPART_MIXED_BOUNDARY "--\r\n",
			attachment_type, attachment_name, localattachment);

	zbx_free(localattachment);

	return body;
}
