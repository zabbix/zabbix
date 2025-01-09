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

#include <termios.h>

#define	ZBX_AT_ESC	"\x1B"
#define ZBX_AT_CTRL_Z	"\x1A"

static int	write_gsm(int fd, const char *str, char *error, int max_error_len)
{
	int	i, wlen, len, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() str:'%s'", __func__, str);

	len = strlen(str);

	for (wlen = 0; wlen < len; wlen += i)
	{
		if (-1 == (i = write(fd, str + wlen, len - wlen)))
		{
			i = 0;

			if (EAGAIN == errno)
				continue;

			zabbix_log(LOG_LEVEL_DEBUG, "error writing to GSM modem: %s", zbx_strerror(errno));
			if (NULL != error)
				zbx_snprintf(error, max_error_len, "error writing to GSM modem: %s", zbx_strerror(errno));

			ret = FAIL;
			break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	check_modem_result(char *buffer, char **ebuf, char **sbuf, const char *expect, char *error,
		int max_error_len)
{
	char	rcv[0xff];
	int	i, len, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_strlcpy(rcv, *sbuf, sizeof(rcv));

	do
	{
		len = *ebuf - *sbuf;
		for (i = 0; i < len && (*sbuf)[i] != '\n' && (*sbuf)[i] != '\r'; i++)
			; /* find first '\r' & '\n' */

		if (i < len)
			(*sbuf)[i++] = '\0';

		ret = (NULL == strstr(*sbuf, expect)) ? FAIL : SUCCEED;

		*sbuf += i;

		if (*sbuf != buffer)
		{
			memmove(buffer, *sbuf, *ebuf - *sbuf + 1); /* +1 for '\0' */
			*ebuf -= *sbuf - buffer;
			*sbuf = buffer;
		}
	}
	while (*sbuf < *ebuf && FAIL == ret);

	if (FAIL == ret && NULL != error)
	{
		zbx_snprintf(error, (size_t)max_error_len, "modem communication error");
		zabbix_log(LOG_LEVEL_WARNING, "modem communication error: expected [%s] received [%s]",
				expect, rcv);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

#define MAX_ATTEMPTS	3

static int	read_gsm(int fd, const char *expect, char *error, int max_error_len, int timeout_sec)
{
	static char	buffer[0xff], *ebuf = buffer, *sbuf = buffer;
	fd_set		fdset;
	struct timeval  tv;
	int		i, nbytes, nbytes_total, rc, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [%s] [%s] [%s] [%s]", __func__, expect,
			ebuf != buffer ? buffer : "NULL", ebuf != buffer ? ebuf : "NULL", ebuf != buffer ? sbuf : "NULL");

	if ('\0' != *expect && ebuf != buffer &&
			SUCCEED == check_modem_result(buffer, &ebuf, &sbuf, expect, error, max_error_len))
	{
		goto out;
	}

	/* make attempts to read until there is a printable character, which would indicate a result of the command */

	for (i = 0; i < MAX_ATTEMPTS; i++)
	{
		tv.tv_sec = timeout_sec / MAX_ATTEMPTS;
		tv.tv_usec = (timeout_sec % MAX_ATTEMPTS) * 1000000 / MAX_ATTEMPTS;

		/* wait for response from modem */

		FD_ZERO(&fdset);
		FD_SET(fd, &fdset);

		while (1)
		{
			rc = select(fd + 1, &fdset, NULL, NULL, &tv);

			if (-1 == rc)
			{
				if (EINTR == errno)
					continue;

				zabbix_log(LOG_LEVEL_DEBUG, "error select() for GSM modem: %s", zbx_strerror(errno));

				if (NULL != error)
				{
					zbx_snprintf(error, max_error_len, "error select() for GSM modem: %s",
							zbx_strerror(errno));
				}

				ret = FAIL;
				goto out;
			}
			else if (0 == rc)
			{
				/* timeout exceeded */

				zabbix_log(LOG_LEVEL_DEBUG, "error during wait for GSM modem");
				if (NULL != error)
					zbx_snprintf(error, max_error_len, "error during wait for GSM modem");

				goto check_result;
			}
			else
				break;
		}

		/* read characters into our string buffer */

		nbytes_total = 0;

		while (0 < (nbytes = read(fd, ebuf, buffer + sizeof(buffer) - 1 - ebuf)))
		{
			ebuf += nbytes;
			*ebuf = '\0';

			nbytes_total += nbytes;

			zabbix_log(LOG_LEVEL_DEBUG, "Read attempt #%d from GSM modem [%s]", i, ebuf - nbytes);
		}

		while (0 < nbytes_total)
		{
			if (0 == isspace(ebuf[-nbytes_total]))
				goto check_result;

			nbytes_total--;
		}
	}

	/* nul terminate the string and see if we got an OK response */
check_result:
	*ebuf = '\0';

	zabbix_log(LOG_LEVEL_DEBUG, "Read from GSM modem [%s]", sbuf);

	if ('\0' == *expect) /* empty */
	{
		sbuf = ebuf = buffer;
		*ebuf = '\0';
		goto out;
	}

	ret = check_modem_result(buffer, &ebuf, &sbuf, expect, error, max_error_len);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

typedef struct
{
	const char	*message;
	const char	*result;
	int		timeout_sec;
}
zbx_sms_scenario;

static int	check_phone_number(const char *number)
{
	const char *ptr;

	for (ptr = number; '\0' != *ptr; ptr++)
	{
		if (0 == isprint(*ptr) || '"' == *ptr)
			return FAIL;
	}

	return SUCCEED;
}

static int	check_sms_message(const char *message)
{
	const char *ptr;

	for (ptr = message; '\0' != *ptr; ptr++)
	{
		if (*ZBX_AT_CTRL_Z == *ptr)
			return FAIL;
	}

	return SUCCEED;
}

int	send_sms(const char *device, const char *number, const char *message, char *error, int max_error_len)
{
	zbx_sms_scenario scenario[] =
	{
		{ZBX_AT_ESC	, NULL		, 0},	/* Send <ESC> */
		{"AT+CMEE=2\r"	, ""/*"OK"*/	, 5},	/* verbose error values */
		{"ATE0\r"	, "OK"		, 5},	/* Turn off echo */
		{"AT\r"		, "OK"		, 5},	/* Init modem */
		{"AT+CMGF=1\r"	, "OK"		, 5},	/* Switch to text mode */
		{"AT+CMGS=\""	, NULL		, 0},	/* Set phone number */
		{number		, NULL		, 0},	/* Write phone number */
		{"\"\r"		, "> "		, 5},	/* Set phone number */
		{message	, NULL		, 0},	/* Write message */
		{ZBX_AT_CTRL_Z	, "+CMGS: "	, 40},	/* Send message */
		{NULL		, "OK"		, 1},	/* ^Z */
		{NULL		, NULL		, 0}
	};

	zbx_sms_scenario	*step;
	struct termios		options, old_options;
	int			f, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);
	if (SUCCEED != check_phone_number(number))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "invalid phone number \"%s\"", number);
		if (NULL != error)
			zbx_snprintf(error, max_error_len, "Invalid phone number \"%s\"", number);

		return FAIL;
	}

	if (SUCCEED != check_sms_message(message))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "invalid message \"%s\"", message);
		if (NULL != error)
			zbx_snprintf(error, max_error_len, "Invalid message \"%s\"", message);

		return FAIL;
	}

	if (-1 == (f = open(device, O_RDWR | O_NOCTTY | O_NDELAY)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "error in open(%s): %s", device, zbx_strerror(errno));
		if (NULL != error)
		{
			zbx_snprintf(error, max_error_len, "Cannot open device \"%s\": %s", device,
					zbx_strerror(errno));
		}
		return FAIL;
	}

	if (-1 == fcntl(f, F_SETFL, 0))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "error in setting the status flag to 0 (for %s): %s", device,
				zbx_strerror(errno));

		if (NULL != error)
		{
			zbx_snprintf(error, (size_t)max_error_len,
					"Cannot set device \"%s\" status flag to 0: %s",
					device, zbx_strerror(errno));
		}
		ret = FAIL;
		goto out;
	}

	/* get ta parameters */
	if (0 != tcgetattr(f, &old_options))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "error in getting modem attributes (for %s): %s", device,
				zbx_strerror(errno));

		if (NULL != error)
		{
			zbx_snprintf(error, (size_t)max_error_len,
					"error in getting modem attributes (for %s): %s",
					device, zbx_strerror(errno));
		}
		ret = FAIL;
		goto out;
	}

	memset(&options, 0, sizeof(options));

	options.c_iflag = IGNCR | INLCR | ICRNL;
#ifdef ONOCR
	options.c_oflag = ONOCR;
#endif
	options.c_cflag = old_options.c_cflag | CRTSCTS | CS8 | CLOCAL | CREAD;
	options.c_lflag &= (tcflag_t)~(ICANON | ECHO | ECHOE | ISIG);
	options.c_cc[VMIN] = 0;
	options.c_cc[VTIME] = 1;

	tcsetattr(f, TCSANOW, &options);

	for (step = scenario; NULL != step->message || NULL != step->result; step++)
	{
		if (NULL != step->message)
		{
			if (message == step->message)
			{
				char	*tmp;

				tmp = zbx_strdup(NULL, message);
				zbx_remove_chars(tmp, "\r");

				ret = write_gsm(f, tmp, error, max_error_len);

				zbx_free(tmp);
			}
			else
				ret = write_gsm(f, step->message, error, max_error_len);

			if (FAIL == ret)
				break;
		}

		if (NULL != step->result)
		{
			if (FAIL == (ret = read_gsm(f, step->result, error, max_error_len, step->timeout_sec)))
				break;
		}
	}

	if (FAIL == ret)
	{
		write_gsm(f, "\r" ZBX_AT_ESC ZBX_AT_CTRL_Z, NULL, 0); /* cancel all */
		read_gsm(f, "", NULL, 0, 0); /* clear buffer */
	}

	tcsetattr(f, TCSANOW, &old_options);
out:
	close(f);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
