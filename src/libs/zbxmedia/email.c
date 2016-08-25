/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "comms.h"
#include "base64.h"

#include "zbxmedia.h"

/* number of characters per line when wrapping Base64 data in Email */
#define ZBX_EMAIL_B64_MAXLINE			76

/* number of characters per "encoded-word" in RFC-2047 message header */
#define ZBX_EMAIL_B64_MAXWORD_RFC2047		75

/* multiple 'encoded-word's should be separated by <CR><LF><SPACE> */
#define ZBX_EMAIL_ENCODED_WORD_SEPARATOR	"\r\n "

/******************************************************************************
 *                                                                            *
 * Function: str_base64_encode_rfc2047                                        *
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
	*p_base64 = zbx_malloc(NULL, p_base64_alloc);
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
		for (p1 = p0, c_len = 0; '\0' != *p1; p1 += c_len)
		{
			/* an invalid UTF-8 character or length of a string more than 45 bytes */
			if (0 == (c_len = zbx_utf8_char_len(p1)) || 45 < p1 - p0 + c_len)
				break;
		}

		if (0 < p1 - p0)
		{
			/* 12 characters are taken by header "=?UTF-8?B?" and trailer "?=" plus '\0' */
			char	b64_buf[ZBX_EMAIL_B64_MAXWORD_RFC2047 - 12 + 1];

			str_base64_encode(p0, b64_buf, p1 - p0);

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
 * Function: smtp_parse_mailbox                                                 *
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
 *             max_error_len - [IN] size of 'error' string                      *
 *             display_name  - [OUT] address of pointer to dynamically          *
 *                             allocated 'display_name' string (ASCII or        *
 *                             base64-encoded)                                  *
 *             angle_addr    - [OUT] address of pointer to dynamically          *
 *                             allocated 'angle_addr' string                    *
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
static int	smtp_parse_mailbox(const char *mailbox, char *error, size_t max_error_len, char **display_name,
		char **angle_addr)
{
	const char	*p, *pstart, *angle_addr_start = NULL, *domain_start = NULL, *utf8_end = NULL;
	const char	*base64_like_start = NULL, *base64_like_end = NULL;
	char		*base64_buf = NULL;
	size_t		size_angle_addr = 0, offset_angle_addr = 0, len, i;
	int		ret = FAIL;

	/* Skip leading whitespace */
	p = mailbox;
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
					zbx_snprintf(error, max_error_len, "invalid UTF-8 character in email"
							" address: %s", mailbox);
					goto out;
				}
			}
			utf8_end = p + len - 1;
			p += len;
		}
		else if (0 == len)	/* invalid UTF-8 character */
		{
			zbx_snprintf(error, max_error_len, "invalid UTF-8 character in email address: %s", mailbox);
			goto out;
		}
	}

	if (NULL == domain_start)
	{
		zbx_snprintf(error, max_error_len, "no '@' in email address: %s", mailbox);
		goto out;
	}

	if (utf8_end > angle_addr_start)
	{
		zbx_snprintf(error, max_error_len, "email address local or domain part contains UTF-8 character: %s",
				mailbox);
		goto out;
	}

	if (NULL != angle_addr_start)
	{
		zbx_snprintf_alloc(angle_addr, &size_angle_addr, &offset_angle_addr, "%s", angle_addr_start);

		if (pstart < angle_addr_start)	/* display name */
		{
			*display_name = zbx_malloc(*display_name, (size_t)(angle_addr_start - pstart + 1));
			memcpy(*display_name, pstart, (size_t)(angle_addr_start - pstart));
			*((*display_name) + (angle_addr_start - pstart)) = '\0';

			/* UTF-8 or Base64-looking display name */
			if (NULL != utf8_end || (NULL != base64_like_end && angle_addr_start - 1 > base64_like_end))
			{
				str_base64_encode_rfc2047(*display_name, &base64_buf);
				zbx_free(*display_name);
				*display_name = base64_buf;
			}
		}
	}
	else
		zbx_snprintf_alloc(angle_addr, &size_angle_addr, &offset_angle_addr, "<%s>", mailbox);

	ret = SUCCEED;
out:
	return ret;
}

static char	*smtp_prepare_payload(const char *from_display_name, const char *from_angle_addr,
		const char *to_display_name, const char *to_angle_addr, const char *mailsubject, const char *mailbody)
{
	char		*tmp = NULL, *base64 = NULL, *base64_lf;
	char		*localsubject = NULL, *localbody = NULL;
	char		str_time[MAX_STRING_LEN];
	struct tm	*local_time;
	time_t		email_time;

	/* prepare subject */

	tmp = string_replace(mailsubject, "\r\n", " ");
	localsubject = string_replace(tmp, "\n", " ");
	zbx_free(tmp);

	if (FAIL == is_ascii_string(localsubject))
	{
		/* split subject into multiple RFC 2047 "encoded-words" */
		str_base64_encode_rfc2047(localsubject, &base64);
		zbx_free(localsubject);

		localsubject = base64;
		base64 = NULL;
	}

	/* prepare body */

	tmp = string_replace(mailbody, "\r\n", "\n");
	localbody = string_replace(tmp, "\n", "\r\n");
	zbx_free(tmp);

	str_base64_encode_dyn(localbody, &base64, strlen(localbody));

	/* wrap base64 encoded data with linefeeds */
	base64_lf = str_linefeed(base64, ZBX_EMAIL_B64_MAXLINE, "\r\n");
	zbx_free(base64);
	base64 = base64_lf;

	zbx_free(localbody);
	localbody = base64;
	base64 = NULL;

	/* prepare date */

	time(&email_time);
	local_time = localtime(&email_time);
	strftime(str_time, MAX_STRING_LEN, "%a, %d %b %Y %H:%M:%S %z", local_time);

	/* e-mails are sent in 'SMTP/MIME e-mail' format because UTF-8 is used both in mailsubject and mailbody */
	/* =?charset?encoding?encoded text?= format must be used for subject field */

	tmp = zbx_dsprintf(tmp,
			"From: %s%s\r\n"
			"To: %s%s\r\n"
			"Date: %s\r\n"
			"Subject: %s\r\n"
			"MIME-Version: 1.0\r\n"
			"Content-Type: text/plain; charset=\"UTF-8\"\r\n"
			"Content-Transfer-Encoding: base64\r\n"
			"\r\n"
			"%s",
			NULL != from_display_name ? from_display_name : "", from_angle_addr,
			NULL != to_display_name ? to_display_name: "", to_angle_addr,
			str_time, localsubject, localbody);

	zbx_free(localsubject);
	zbx_free(localbody);

	return tmp;
}

#ifdef HAVE_SMTP_AUTHENTICATION
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

static int	send_email_plain(const char *smtp_server, unsigned short smtp_port, const char *smtp_helo,
		const char *from_display_name, const char *from_angle_addr, const char *to_display_name,
		const char *to_angle_addr, const char *mailsubject, const char *mailbody, int timeout,
		char *error, size_t max_error_len)
{
	zbx_socket_t	s;
	int		err, ret = FAIL;
	char		cmd[MAX_STRING_LEN], *cmdp = NULL;

	const char	*OK_220 = "220";
	const char	*OK_250 = "250";
	const char	*OK_251 = "251";
	const char	*OK_354 = "354";
	const char	*response;

	zbx_alarm_on(timeout);

	/* connect to and receive an initial greeting from SMTP server */

	if (FAIL == zbx_tcp_connect(&s, CONFIG_SOURCE_IP, smtp_server, smtp_port, 0, ZBX_TCP_SEC_UNENCRYPTED, NULL,
			NULL))
	{
		zbx_snprintf(error, max_error_len, "cannot connect to SMTP server \"%s\": %s",
				smtp_server, zbx_socket_strerror());
		goto out;
	}

	if (FAIL == smtp_readln(&s, &response))
	{
		zbx_snprintf(error, max_error_len, "error receiving initial string from SMTP server: %s",
				zbx_strerror(errno));
		goto close;
	}

	if (0 != strncmp(response, OK_220, strlen(OK_220)))
	{
		zbx_snprintf(error, max_error_len, "no welcome message 220* from SMTP server \"%s\"", response);
		goto close;
	}

	/* send HELO */

	if ('\0' != *smtp_helo)
	{
		zbx_snprintf(cmd, sizeof(cmd), "HELO %s\r\n", smtp_helo);

		if (-1 == write(s.socket, cmd, strlen(cmd)))
		{
			zbx_snprintf(error, max_error_len, "error sending HELO to mailserver: %s",
					zbx_strerror(errno));
			goto close;
		}

		if (FAIL == smtp_readln(&s, &response))
		{
			zbx_snprintf(error, max_error_len, "error receiving answer on HELO request: %s",
					zbx_strerror(errno));
			goto close;
		}

		if (0 != strncmp(response, OK_250, strlen(OK_250)))
		{
			zbx_snprintf(error, max_error_len, "wrong answer on HELO \"%s\"", response);
			goto close;
		}
	}

	/* send MAIL FROM */

	zbx_snprintf(cmd, sizeof(cmd), "MAIL FROM:%s\r\n", from_angle_addr);

	if (-1 == write(s.socket, cmd, strlen(cmd)))
	{
		zbx_snprintf(error, max_error_len, "error sending MAIL FROM to mailserver: %s", zbx_strerror(errno));
		goto close;
	}

	if (FAIL == smtp_readln(&s, &response))
	{
		zbx_snprintf(error, max_error_len, "error receiving answer on MAIL FROM request: %s", zbx_strerror(errno));
		goto close;
	}

	if (0 != strncmp(response, OK_250, strlen(OK_250)))
	{
		zbx_snprintf(error, max_error_len, "wrong answer on MAIL FROM \"%s\"", response);
		goto close;
	}

	/* send RCPT TO */

	zbx_snprintf(cmd, sizeof(cmd), "RCPT TO:%s\r\n", to_angle_addr);

	if (-1 == write(s.socket, cmd, strlen(cmd)))
	{
		zbx_snprintf(error, max_error_len, "error sending RCPT TO to mailserver: %s", zbx_strerror(errno));
		goto close;
	}

	if (FAIL == smtp_readln(&s, &response))
	{
		zbx_snprintf(error, max_error_len, "error receiving answer on RCPT TO request: %s", zbx_strerror(errno));
		goto close;
	}

	/* May return 251 as well: User not local; will forward to <forward-path>. See RFC825. */
	if (0 != strncmp(response, OK_250, strlen(OK_250)) && 0 != strncmp(response, OK_251, strlen(OK_251)))
	{
		zbx_snprintf(error, max_error_len, "wrong answer on RCPT TO \"%s\"", response);
		goto close;
	}

	/* send DATA */

	zbx_snprintf(cmd, sizeof(cmd), "DATA\r\n");

	if (-1 == write(s.socket, cmd, strlen(cmd)))
	{
		zbx_snprintf(error, max_error_len, "error sending DATA to mailserver: %s", zbx_strerror(errno));
		goto close;
	}

	if (FAIL == smtp_readln(&s, &response))
	{
		zbx_snprintf(error, max_error_len, "error receiving answer on DATA request: %s", zbx_strerror(errno));
		goto close;
	}

	if (0 != strncmp(response, OK_354, strlen(OK_354)))
	{
		zbx_snprintf(error, max_error_len, "wrong answer on DATA \"%s\"", response);
		goto close;
	}

	cmdp = smtp_prepare_payload(from_display_name, from_angle_addr, to_display_name, to_angle_addr, mailsubject,
			mailbody);
	err = write(s.socket, cmdp, strlen(cmdp));
	zbx_free(cmdp);

	if (-1 == err)
	{
		zbx_snprintf(error, max_error_len, "error sending headers and mail body to mailserver: %s",
				zbx_strerror(errno));
		goto close;
	}

	/* send . */

	zbx_snprintf(cmd, sizeof(cmd), "\r\n.\r\n");

	if (-1 == write(s.socket, cmd, strlen(cmd)))
	{
		zbx_snprintf(error, max_error_len, "error sending . to mailserver: %s", zbx_strerror(errno));
		goto close;
	}

	if (FAIL == smtp_readln(&s, &response))
	{
		zbx_snprintf(error, max_error_len, "error receiving answer on . request: %s", zbx_strerror(errno));
		goto close;
	}

	if (0 != strncmp(response, OK_250, strlen(OK_250)))
	{
		zbx_snprintf(error, max_error_len, "wrong answer on end of data \"%s\"", response);
		goto close;
	}

	/* send QUIT */

	zbx_snprintf(cmd, sizeof(cmd), "QUIT\r\n");

	if (-1 == write(s.socket, cmd, strlen(cmd)))
	{
		zbx_snprintf(error, max_error_len, "error sending QUIT to mailserver: %s", zbx_strerror(errno));
		goto close;
	}

	ret = SUCCEED;
close:
	zbx_tcp_close(&s);
out:
	zbx_alarm_off();

	return ret;
}

static int	send_email_curl(const char *smtp_server, unsigned short smtp_port, const char *smtp_helo,
		const char *from_display_name, const char *from_angle_addr, const char *to_display_name,
		const char *to_angle_addr, const char *mailsubject, const char *mailbody,
		unsigned char smtp_security, unsigned char smtp_verify_peer, unsigned char smtp_verify_host,
		unsigned char smtp_authentication, const char *username, const char *password, int timeout,
		char *error, size_t max_error_len)
{
#ifdef HAVE_SMTP_AUTHENTICATION
	int			err, ret = FAIL;
	CURL            	*easyhandle;
	char			url[MAX_STRING_LEN], errbuf[CURL_ERROR_SIZE];
	size_t			url_offset= 0;
	struct curl_slist	*recipients = NULL;
	smtp_payload_status_t	payload_status = {};

	if (NULL == (easyhandle = curl_easy_init()))
	{
		zbx_strlcpy(error, "cannot initialize cURL library", max_error_len);
		goto out;
	}

	if (SMTP_SECURITY_SSL == smtp_security)
		url_offset += zbx_snprintf(url + url_offset, sizeof(url) - url_offset, "smtps://");
	else
		url_offset += zbx_snprintf(url + url_offset, sizeof(url) - url_offset, "smtp://");

	url_offset += zbx_snprintf(url + url_offset, sizeof(url) - url_offset, "%s:%hu", smtp_server, smtp_port);

	if ('\0' != *smtp_helo)
		url_offset += zbx_snprintf(url + url_offset, sizeof(url) - url_offset, "/%s", smtp_helo);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_URL, url)))
		goto error;

	if (SMTP_SECURITY_NONE != smtp_security)
	{
		extern char	*CONFIG_SSL_CA_LOCATION;

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYPEER,
						0 == smtp_verify_peer ? 0L : 1L)) ||
				CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYHOST,
						0 == smtp_verify_host ? 0L : 2L)))
		{
			goto error;
		}

		if (0 != smtp_verify_peer && NULL != CONFIG_SSL_CA_LOCATION)
		{
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_CAPATH, CONFIG_SSL_CA_LOCATION)))
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
#if 0x071e00 >= LIBCURL_VERSION_NUM	/* versions 7.20.0 to 7.30.0 do not support specifying login options */
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_USERNAME, username)) ||
				CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PASSWORD, password)))
		{
			goto error;
		}
#elif 0x072100 >= LIBCURL_VERSION_NUM	/* versions 7.31.0 to 7.33.0 support login options in CURLOPT_USERPWD */
		char	userpwd[523];

		zbx_snprintf(userpwd, sizeof(userpwd), "%s:%s;AUTH=PLAIN", username, password);

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_USERPWD, userpwd)))
			goto error;
#else					/* versions 7.34.0 and above support explicit CURLOPT_LOGIN_OPTIONS */
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_USERNAME, username)) ||
				CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PASSWORD, password)) ||
				CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_LOGIN_OPTIONS, "AUTH=PLAIN")))
		{
			goto error;
		}
#endif
	}

	recipients = curl_slist_append(recipients, to_angle_addr);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_MAIL_FROM, from_angle_addr)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_MAIL_RCPT, recipients)))
	{
		goto error;
	}

	payload_status.payload = smtp_prepare_payload(from_display_name, from_angle_addr, to_display_name,
			to_angle_addr, mailsubject, mailbody);
	payload_status.payload_len = strlen(payload_status.payload);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_UPLOAD, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_READFUNCTION, smtp_provide_payload)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_READDATA, &payload_status)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_TIMEOUT, (long)timeout)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_ERRORBUFFER, errbuf)))
	{
		goto error;
	}

	if (NULL != CONFIG_SOURCE_IP)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_INTERFACE, CONFIG_SOURCE_IP)))
			goto error;
	}

	if (SUCCEED == zabbix_check_log_level(LOG_LEVEL_TRACE))
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_VERBOSE, 1L)))
			goto error;

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_DEBUGFUNCTION, smtp_debug_function)))
			goto error;
	}

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		zbx_snprintf(error, max_error_len, "%s: %s", curl_easy_strerror(err), errbuf);
		goto clean;
	}

	ret = SUCCEED;
	goto clean;
error:
	zbx_strlcpy(error, curl_easy_strerror(err), max_error_len);
clean:
	zbx_free(payload_status.payload);

	curl_slist_free_all(recipients);
	curl_easy_cleanup(easyhandle);
out:
	return ret;
#else
	zbx_strlcpy(error, "Support for SMTP authentication was not compiled in", max_error_len);
	return FAIL;
#endif
}

int	send_email(const char *smtp_server, unsigned short smtp_port, const char *smtp_helo,
		const char *smtp_email, const char *mailto, const char *mailsubject, const char *mailbody,
		unsigned char smtp_security, unsigned char smtp_verify_peer, unsigned char smtp_verify_host,
		unsigned char smtp_authentication, const char *username, const char *password, int timeout,
		char *error, size_t max_error_len)
{
	const char	*__function_name = "send_email";

	int		ret = FAIL;
	char		*from_display_name = NULL, *from_angle_addr = NULL;
	char		*to_display_name = NULL, *to_angle_addr = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() smtp_server:'%s' smtp_port:%hu smtp_security:%d smtp_authentication:%d",
			__function_name, smtp_server, smtp_port, (int)smtp_security, (int)smtp_authentication);

	*error = '\0';

	/* validate addresses before connecting to the server */

	if (SUCCEED != smtp_parse_mailbox(smtp_email, error, max_error_len, &from_display_name, &from_angle_addr))
		goto clean;

	if (SUCCEED != smtp_parse_mailbox(mailto, error, max_error_len, &to_display_name, &to_angle_addr))
		goto clean;

	/* choose appropriate method for sending the email */

	if (SMTP_SECURITY_NONE == smtp_security && SMTP_AUTHENTICATION_NONE == smtp_authentication)
	{
		ret = send_email_plain(smtp_server, smtp_port, smtp_helo, from_display_name, from_angle_addr,
				to_display_name, to_angle_addr, mailsubject, mailbody, timeout, error, max_error_len);
	}
	else
	{
		ret = send_email_curl(smtp_server, smtp_port, smtp_helo, from_display_name, from_angle_addr,
				to_display_name, to_angle_addr, mailsubject, mailbody, smtp_security, smtp_verify_peer,
				smtp_verify_host, smtp_authentication, username, password, timeout, error,
				max_error_len);
	}
clean:
	zbx_free(from_display_name);
	zbx_free(to_display_name);
	zbx_free(from_angle_addr);
	zbx_free(to_angle_addr);

	if ('\0' != *error)
		zabbix_log(LOG_LEVEL_WARNING, "%s", error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
