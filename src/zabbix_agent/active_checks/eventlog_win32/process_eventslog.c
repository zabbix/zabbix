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

#include "process_eventslog.h"
#include "severity_constants.h"

#include "../../metrics/metrics.h"
#include "../../logfiles/logfiles.h"

#include "zbxlog.h"
#include "zbxregexp.h"
#include "zbxstr.h"
#include "zbx_item_constants.h"
#include "zbxalgo.h"
#include "zbxcomms.h"
#include "zbxlog.h"

#include <strsafe.h> /* StringCchPrintf */

#define	EVENTLOG_REG_PATH TEXT("SYSTEM\\CurrentControlSet\\Services\\EventLog\\")

/******************************************************************************
 *                                                                            *
 * Purpose: gets event message and parameter translation files from registry  *
 *                                                                            *
 * Parameters: szLogName         - [IN]                                       *
 *             szSourceName      - [IN] log source name                       *
 *             pEventMessageFile - [OUT]                                      *
 *             pParamMessageFile - [OUT]                                      *
 *                                                                            *
 ******************************************************************************/
static void	zbx_get_message_files(const wchar_t *szLogName, const wchar_t *szSourceName,
		wchar_t **pEventMessageFile, wchar_t **pParamMessageFile)
{
	wchar_t	buf[MAX_PATH];
	HKEY	hKey = NULL;
	DWORD	szData = 0;

	/* Get path to message dll */
	StringCchPrintf(buf, MAX_PATH, EVENTLOG_REG_PATH TEXT("%s\\%s"), szLogName, szSourceName);

	if (ERROR_SUCCESS != RegOpenKeyEx(HKEY_LOCAL_MACHINE, buf, 0, KEY_READ, &hKey))
		return;

	if (ERROR_SUCCESS == RegQueryValueEx(hKey, TEXT("EventMessageFile"), NULL, NULL, NULL, &szData))
	{
		*pEventMessageFile = zbx_malloc(*pEventMessageFile, szData);
		if (ERROR_SUCCESS != RegQueryValueEx(hKey, TEXT("EventMessageFile"), NULL, NULL,
				(unsigned char *)*pEventMessageFile, &szData))
		{
			zbx_free(*pEventMessageFile);
		}
	}

	if (ERROR_SUCCESS == RegQueryValueEx(hKey, TEXT("ParameterMessageFile"), NULL, NULL, NULL, &szData))
	{
		*pParamMessageFile = zbx_malloc(*pParamMessageFile, szData);
		if (ERROR_SUCCESS != RegQueryValueEx(hKey, TEXT("ParameterMessageFile"), NULL, NULL,
				(unsigned char *)*pParamMessageFile, &szData))
		{
			zbx_free(*pParamMessageFile);
		}
	}

	RegCloseKey(hKey);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Loads the specified message file, expanding environment variables *
 *          in the file name if necessary.                                    *
 *                                                                            *
 * Parameters: szFileName - [IN] message file name                            *
 *                                                                            *
 * Return value: handle to loaded library or NULL otherwise                   *
 *                                                                            *
 ******************************************************************************/
static HINSTANCE	zbx_load_message_file(const wchar_t *szFileName)
{
	wchar_t		*dll_name = NULL;
	long int	sz, len = 0;
	HINSTANCE	res = NULL;

	if (NULL == szFileName)
		return NULL;

	do
	{
		if (0 != (sz = len))
			dll_name = zbx_realloc(dll_name, sz * sizeof(wchar_t));

		len = ExpandEnvironmentStrings(szFileName, dll_name, sz);
	}
	while (0 != len && sz < len);

	if (0 != len)
		res = LoadLibraryEx(dll_name, NULL, LOAD_LIBRARY_AS_DATAFILE);

	zbx_free(dll_name);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: extracts specified message from message file                      *
 *                                                                            *
 * Parameters: hLib           - [IN] message file handle                      *
 *             dwMessageId    - [IN]                                          *
 *             pInsertStrings - [IN] list of insert strings, optional         *
 *                                                                            *
 * Return value: formatted message converted to utf8 or NULL                  *
 *                                                                            *
 * Comments: This function allocates memory for the returned message, which   *
 *           must be freed by the caller later.                               *
 *                                                                            *
 ******************************************************************************/
static char	*zbx_format_message(HINSTANCE hLib, DWORD dwMessageId, wchar_t **pInsertStrings)
{
	wchar_t *pMsgBuf = NULL;
	char	*message;

	if (0 == FormatMessage(FORMAT_MESSAGE_FROM_HMODULE | FORMAT_MESSAGE_ALLOCATE_BUFFER |
			FORMAT_MESSAGE_ARGUMENT_ARRAY | FORMAT_MESSAGE_MAX_WIDTH_MASK,
			hLib, dwMessageId, MAKELANGID(LANG_NEUTRAL, SUBLANG_ENGLISH_US), (wchar_t *)&pMsgBuf, 0,
			(va_list *)pInsertStrings))
	{
		return NULL;
	}

	message = zbx_unicode_to_utf8(pMsgBuf);
	zbx_rtrim(message, "\r\n ");

	LocalFree((HLOCAL)pMsgBuf);

	return message;
}

/******************************************************************************
 *                                                                            *
 * Purpose: translates message by replacing parameters %%<id> with translated *
 *          values                                                            *
 *                                                                            *
 * Parameters: message - [IN/OUT] message to translate                        *
 *             hLib    - [IN] parameter message file handle                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_translate_message_params(char **message, HINSTANCE hLib)
{
	char	*param, *pstart, *pend;
	int	dwMessageId;
	size_t	offset = 0;

	while (1)
	{
		if (NULL == (pstart = strstr(*message + offset, "%%")))
			break;

		pend = pstart + 2;

		dwMessageId = atoi(pend);

		while ('\0' != *pend && 0 != isdigit(*pend))
			pend++;

		offset = pend - *message - 1;

		if (NULL != (param = zbx_format_message(hLib, dwMessageId, NULL)))
		{
			zbx_replace_string(message, pstart - *message, &offset, param);

			zbx_free(param);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses single Event Log record                                    *
 *                                                                            *
 * Parameters: wsource       - [IN] Event Log file name                       *
 *             pELR          - [IN] buffer with single Event Log Record       *
 *             ...           - [OUT] ELR detail                               *
 *                                                                            *
 ******************************************************************************/
static void	zbx_parse_eventlog_message(const wchar_t *wsource, const EVENTLOGRECORD *pELR, char **out_source,
		char **out_message, unsigned short *out_severity, unsigned long *out_timestamp,
		unsigned long *out_eventid)
{
#define MAX_INSERT_STRS 100
	wchar_t	*pEventMessageFile = NULL, *pParamMessageFile = NULL, *pFile = NULL, *pNextFile = NULL, *pCh,
			*aInsertStrings[MAX_INSERT_STRS];
	HINSTANCE	hLib = NULL, hParamLib = NULL;
	long		i;
	int		err;

	memset(aInsertStrings, 0, sizeof(aInsertStrings));

	*out_message = NULL;
	*out_severity = pELR->EventType;				/* return event type */
	*out_timestamp = pELR->TimeGenerated;				/* return timestamp */
	*out_eventid = pELR->EventID & 0xffff;
	*out_source = zbx_unicode_to_utf8((wchar_t *)(pELR + 1));	/* copy source name */

	/* get message file names */
	zbx_get_message_files(wsource, (wchar_t *)(pELR + 1), &pEventMessageFile, &pParamMessageFile);

	/* prepare insert string array */
	if (0 < pELR->NumStrings)
	{
		pCh = (wchar_t *)((unsigned char *)pELR + pELR->StringOffset);

		for (i = 0; i < pELR->NumStrings && i < MAX_INSERT_STRS; i++)
		{
			aInsertStrings[i] = pCh;
			pCh += wcslen(pCh) + 1;
		}
	}

	err = FAIL;

	for (pFile = pEventMessageFile; NULL != pFile && err != SUCCEED; pFile = pNextFile)
	{
		if (NULL != (pNextFile = wcschr(pFile, TEXT(';'))))
		{
			*pNextFile = '\0';
			pNextFile++;
		}

		if (NULL != (hLib = zbx_load_message_file(pFile)))
		{
			if (NULL != (*out_message = zbx_format_message(hLib, pELR->EventID, aInsertStrings)))
			{
				err = SUCCEED;

				if (NULL != (hParamLib = zbx_load_message_file(pParamMessageFile)))
				{
					zbx_translate_message_params(out_message, hParamLib);
					FreeLibrary(hParamLib);
				}
			}

			FreeLibrary(hLib);
		}
	}

	zbx_free(pEventMessageFile);
	zbx_free(pParamMessageFile);

	if (SUCCEED != err)
	{
		*out_message = zbx_strdcatf(*out_message, "The description for Event ID:%lu in Source:'%s'"
				" cannot be found. The local computer may not have the necessary registry"
				" information or message DLL files to display messages from a remote computer.",
				*out_eventid, NULL == *out_source ? "" : *out_source);

		if (0 < pELR->NumStrings)
		{
			char	*buf;

			*out_message = zbx_strdcat(*out_message, " The following information is part of the event: ");

			for (i = 0, pCh = (wchar_t *)((unsigned char *)pELR + pELR->StringOffset);
					i < pELR->NumStrings;
					i++, pCh += wcslen(pCh) + 1)
			{
				if (0 < i)
					*out_message = zbx_strdcat(*out_message, "; ");

				buf = zbx_unicode_to_utf8(pCh);
				*out_message = zbx_strdcat(*out_message, buf);
				zbx_free(buf);
			}
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
#undef MAX_INSERT_STRS
}

/* opens event logger and returns number of records */
static int	zbx_open_eventlog(LPCTSTR wsource, HANDLE *eventlog_handle, zbx_uint64_t *FirstID,
		zbx_uint64_t *LastID, DWORD *error_code)
{
	wchar_t	reg_path[MAX_PATH];
	HKEY	hk = NULL;
	DWORD	dwNumRecords, dwOldestRecord;
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	*eventlog_handle = NULL;

	/* Get path to eventlog */
	StringCchPrintf(reg_path, MAX_PATH, EVENTLOG_REG_PATH TEXT("%s"), wsource);

	if (ERROR_SUCCESS != RegOpenKeyEx(HKEY_LOCAL_MACHINE, reg_path, 0, KEY_READ, &hk))
	{
		*error_code = GetLastError();
		goto out;
	}

	RegCloseKey(hk);

	if (NULL == (*eventlog_handle = OpenEventLog(NULL, wsource)))	/* open log file */
	{
		*error_code = GetLastError();
		goto out;
	}

	if (0 == GetNumberOfEventLogRecords(*eventlog_handle, &dwNumRecords) ||
			0 == GetOldestEventLogRecord(*eventlog_handle, &dwOldestRecord))
	{
		*error_code = GetLastError();
		CloseEventLog(*eventlog_handle);
		*eventlog_handle = NULL;
		goto out;
	}

	*FirstID = dwOldestRecord;
	*LastID = dwOldestRecord + dwNumRecords - 1;

	zabbix_log(LOG_LEVEL_DEBUG, "FirstID:" ZBX_FS_UI64 " LastID:" ZBX_FS_UI64 " numIDs:%lu",
			*FirstID, *LastID, dwNumRecords);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/* close event logger */
static void	zbx_close_eventlog(HANDLE eventlog_handle)
{
	if (NULL != eventlog_handle)
		CloseEventLog(eventlog_handle);
}

/******************************************************************************
 *                                                                            *
 * Purpose: tries to set reading position in Event Log                        *
 *                                                                            *
 * Parameters: eventlog_handle - [IN] handle to Event Log to be read          *
 *             FirstID         - [IN] first Event Log record to be parsed     *
 *             ReadDirection   - [IN] direction of reading:                   *
 *                                    EVENTLOG_FORWARDS_READ or               *
 *                                    EVENTLOG_BACKWARDS_READ                 *
 *             LastID          - [IN] position of last record in Event Log    *
 *             eventlog_name   - [IN]                                         *
 *             pELRs           - [IN/OUT] buffer for reading Event Log data   *
 *             buffer_size     - [IN/OUT] size of pELRs                       *
 *             num_bytes_read  - [OUT] number of bytes read from Event Log    *
 *             error_code      - [OUT] (e.g. from ReadEventLog())             *
 *             error           - [OUT] error message in case of failure       *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	seek_eventlog(HANDLE *eventlog_handle, zbx_uint64_t FirstID, DWORD ReadDirection, zbx_uint64_t LastID,
		const char *eventlog_name, BYTE **pELRs, int *buffer_size, DWORD *num_bytes_read, DWORD *error_code,
		char **error)
{
	DWORD		dwRecordNumber, required_buf_size;
	zbx_uint64_t	skip_count = 0;

	/* convert to DWORD to handle possible event record number wraparound */
	dwRecordNumber = (DWORD)FirstID;

	*error_code = ERROR_SUCCESS;

	while (ERROR_SUCCESS == *error_code)
	{
		if (0 != ReadEventLog(eventlog_handle, EVENTLOG_SEEK_READ | EVENTLOG_FORWARDS_READ, dwRecordNumber,
				*pELRs, *buffer_size, num_bytes_read, &required_buf_size))
		{
			return SUCCEED;
		}

		if (ERROR_INVALID_PARAMETER == (*error_code = GetLastError()))
		{
			/* See Microsoft Knowledge Base article, 177199 "BUG: ReadEventLog Fails with Error 87" */
			/* how ReadEventLog() can fail with all valid parameters. */
			/* Error code 87 is named ERROR_INVALID_PARAMETER. */
			break;
		}

		if (ERROR_HANDLE_EOF == *error_code)
			return SUCCEED;

		if (ERROR_INSUFFICIENT_BUFFER == *error_code)
		{
			*buffer_size = required_buf_size;
			*pELRs = (BYTE *)zbx_realloc((void *)*pELRs, *buffer_size);
			*error_code = ERROR_SUCCESS;
			continue;
		}

		*error = zbx_dsprintf(*error, "Cannot read eventlog '%s': %s.", eventlog_name,
				zbx_strerror_from_system(*error_code));
		return FAIL;
	}

	if (EVENTLOG_FORWARDS_READ == ReadDirection)
	{
		/* Error 87 when reading forwards is handled outside this function */
		*error_code = ERROR_SUCCESS;
		return SUCCEED;
	}

	/* fallback implementation to deal with Error 87 when reading backwards */

	if (ERROR_INVALID_PARAMETER == *error_code)
	{
		if (LastID == FirstID)
			skip_count = 1;
		else
			skip_count = LastID - FirstID;

		zabbix_log(LOG_LEVEL_DEBUG, "In %s(): fallback error_code=%d skip_count="ZBX_FS_UI64, __func__,
				*error_code, skip_count);
	}

	*error_code = ERROR_SUCCESS;

	while (0 < skip_count && ERROR_SUCCESS == *error_code)
	{
		BYTE	*pEndOfRecords, *pELR;

		if (0 == ReadEventLog(eventlog_handle, EVENTLOG_SEQUENTIAL_READ | ReadDirection, 0, *pELRs,
				*buffer_size, num_bytes_read, &required_buf_size))
		{
			if (ERROR_INSUFFICIENT_BUFFER == (*error_code = GetLastError()))
			{
				*error_code = ERROR_SUCCESS;
				*buffer_size = required_buf_size;
				*pELRs = (BYTE *)zbx_realloc((void *)*pELRs, *buffer_size);
				continue;
			}

			if (ERROR_HANDLE_EOF != *error_code)
				break;

			*error = zbx_dsprintf(*error, "Cannot read eventlog '%s': %s.", eventlog_name,
					zbx_strerror_from_system(*error_code));
			return FAIL;
		}

		pELR = *pELRs;
		pEndOfRecords = *pELRs + *num_bytes_read;
		*num_bytes_read = 0;	/* we can't reuse the buffer value because of the sort order */

		while (pELR < pEndOfRecords)
		{
			if (0 == --skip_count)
				break;

			pELR += ((PEVENTLOGRECORD)pELR)->Length;
		}
	}

	if (ERROR_HANDLE_EOF == *error_code)
		*error_code = ERROR_SUCCESS;

	return SUCCEED;
}

/********************************************************************************
 *                                                                              *
 * Purpose:  processes Event Log file in batch                                  *
 *                                                                              *
 * Parameters: addrs              - [IN] vector for passing server and port     *
 *                                       where to send data                     *
 *             agent2_result      - [IN] address of buffer where to store       *
 *                                       matching log records (used only in     *
 *                                       Agent2)                                *
 *             eventlog_name      - [IN]                                        *
 *             regexps            - [IN] set of regexp rules for Event Log test *
 *             pattern            - [IN] regular expression or global regular   *
 *                                       expression name (@<global regexp       *
 *                                       name>).                                *
 *             key_severity       - [IN] severity of logged data sources        *
 *             key_source         - [IN] name of logged data source             *
 *             key_logeventid     - [IN] application-specific identifier for    *
 *                                       event                                  *
 *             rate               - [IN] threshold of records count at a time   *
 *             process_value_cb   - [IN] callback function for sending data to  *
 *                                       server                                 *
 *             config_tls         - [IN]                                        *
 *             config_timeout     - [IN]                                        *
 *             config_source_ip   - [IN]                                        *
 *             config_hostname    - [IN]                                        *
 *             config_buffer_send - [IN]                                        *
 *             config_buffer_size - [IN]                                        *
 *             metric             - [IN/OUT] parameters for Event Log process   *
 *             lastlogsize_sent   - [OUT] position of last record sent to       *
 *                                        server                                *
 *             error              - [OUT] error message in case of failure      *
 *                                                                              *
 * Return value: SUCCEED or FAIL                                                *
 *                                                                              *
 ********************************************************************************/
int	process_eventslog(zbx_vector_addr_ptr_t *addrs, zbx_vector_ptr_t *agent2_result, const char
		*eventlog_name, zbx_vector_expression_t *regexps, const char *pattern, const char *key_severity,
		const char *key_source, const char *key_logeventid, int rate, zbx_process_value_func_t process_value_cb,
		const zbx_config_tls_t *config_tls, int config_timeout, const char *config_source_ip,
		const char *config_hostname, int config_buffer_send, int config_buffer_size,
		zbx_active_metric_t *metric, zbx_uint64_t *lastlogsize_sent, char **error)
{
#define EVT_LOG_ITEM 0
#define EVT_LOG_COUNT_ITEM 1
	HANDLE		eventlog_handle = NULL;
	wchar_t		*eventlog_name_w;
	zbx_uint64_t	FirstID, LastID, lastlogsize;
	DWORD		num_bytes_read = 0, required_buf_size, ReadDirection, error_code;
	BYTE		*pELRs = NULL;
	int		ret = FAIL, send_err = SUCCEED, match = SUCCEED, buffer_size = 64 * ZBX_KIBIBYTE, s_count,
			p_count, evt_item_type;
	unsigned long	timestamp = 0;
	char		*source;

	lastlogsize = metric->lastlogsize;
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() source:'%s' lastlogsize:" ZBX_FS_UI64, __func__, eventlog_name,
			lastlogsize);

	/* From MSDN documentation:                                                                         */
	/* The RecordNumber member of EVENTLOGRECORD contains the record number for the Event Log record.   */
	/* The very first record written to an Event Log is record number 1, and other records are          */
	/* numbered sequentially. If the record number reaches ULONG_MAX, the next record number will be 0, */
	/* not 1; however, you use zero to seek to the record.                                              */
	/*                                                                                                  */
	/* This RecordNumber wraparound is handled simply by using 64bit integer to calculate record        */
	/* numbers and then converting to DWORD values.                                                     */

	if (0 != (ZBX_METRIC_FLAG_LOG_COUNT & metric->flags))
		evt_item_type = EVT_LOG_COUNT_ITEM;
	else
		evt_item_type = EVT_LOG_ITEM;

	if (NULL == eventlog_name || '\0' == *eventlog_name)
	{
		*error = zbx_strdup(*error, "Cannot open eventlog with empty name.");
		return ret;
	}

	eventlog_name_w = zbx_utf8_to_unicode(eventlog_name);

	if (SUCCEED != zbx_open_eventlog(eventlog_name_w, &eventlog_handle, &FirstID, &LastID, &error_code))
	{
		*error = zbx_dsprintf(*error, "Cannot open eventlog '%s': %s.", eventlog_name,
				zbx_strerror_from_system(error_code));
		goto out;
	}

	if (1 == metric->skip_old_data)
	{
		metric->lastlogsize = lastlogsize = LastID;
		metric->skip_old_data = 0;
		zabbix_log(LOG_LEVEL_DEBUG, "skipping existing data: lastlogsize:" ZBX_FS_UI64, metric->lastlogsize);
		goto finish;
	}

	/* Having lastlogsize greater than LastID means that there was oldest event record */
	/* (FirstID) wraparound. In this case we must also wrap the lastlogsize value.     */
	if (lastlogsize > LastID)
		lastlogsize = (DWORD)lastlogsize;

	ReadDirection = ((LastID - FirstID) / 2) > lastlogsize ? EVENTLOG_FORWARDS_READ : EVENTLOG_BACKWARDS_READ;

	/* if the lastlogsize is still outside log record interval reset it to the oldest record number, */
	/* otherwise set FirstID to the next record after lastlogsize, which is the first event record   */
	/* to read                                                                                       */
	if (lastlogsize > LastID || lastlogsize < FirstID)
	{
		lastlogsize = FirstID;
		ReadDirection = 0;
	}
	else
		FirstID = lastlogsize + 1;

	pELRs = (BYTE*)zbx_malloc((void *)pELRs, buffer_size);

	if (0 == ReadDirection)		/* read eventlog from the first record */
	{
		error_code = ERROR_SUCCESS;
	}
	else if (LastID < FirstID)	/* no new records */
	{
		error_code = ERROR_HANDLE_EOF;
	}
	else if (SUCCEED != seek_eventlog(eventlog_handle, FirstID, ReadDirection, LastID, eventlog_name, &pELRs,
			&buffer_size, &num_bytes_read, &error_code, error))
	{
		goto out;
	}

	zabbix_log(LOG_LEVEL_TRACE, "%s(): state before EventLog reading: num_bytes_read=%u error=%s FirstID="
			ZBX_FS_UI64 " LastID=" ZBX_FS_UI64 " lastlogsize=" ZBX_FS_UI64, __func__,
			(unsigned int)num_bytes_read, zbx_strerror_from_system(error_code), FirstID, LastID,
			lastlogsize);

	if (ERROR_HANDLE_EOF == error_code)
		goto finish;

	s_count = 0;
	p_count = 0;

	/* Read blocks of records until you reach the end of the log or an           */
	/* error occurs. The records are read from oldest to newest. If the buffer   */
	/* is not big enough to hold a complete event record, reallocate the buffer. */
	while (ERROR_SUCCESS == error_code)
	{
		BYTE	*pELR, *pEndOfRecords;

		if (0 == num_bytes_read && 0 == ReadEventLog(eventlog_handle,
				EVENTLOG_SEQUENTIAL_READ | EVENTLOG_FORWARDS_READ, 0,
				pELRs, buffer_size, &num_bytes_read, &required_buf_size))
		{
			if (ERROR_INSUFFICIENT_BUFFER == (error_code = GetLastError()))
			{
				error_code = ERROR_SUCCESS;
				buffer_size = required_buf_size;
				pELRs = (BYTE *)zbx_realloc((void *)pELRs, buffer_size);
				continue;
			}

			if (ERROR_HANDLE_EOF == error_code)
				break;

			*error = zbx_dsprintf(*error, "Cannot read eventlog '%s': %s.", eventlog_name,
					zbx_strerror_from_system(error_code));
			goto out;
		}

		pELR = pELRs;
		pEndOfRecords = pELR + num_bytes_read;

		zabbix_log(LOG_LEVEL_TRACE, "%s(): state before buffer parsing: num_bytes_read = %u RecordNumber = %d"
				"FirstID = "ZBX_FS_UI64" LastID = "ZBX_FS_UI64" lastlogsize="ZBX_FS_UI64,
				__func__, (unsigned int)num_bytes_read, ((PEVENTLOGRECORD)pELR)->RecordNumber,
				FirstID, LastID, lastlogsize);
		num_bytes_read = 0;

		while (pELR < pEndOfRecords)
		{
			int	delay;
			/* to prevent mismatch in comparing with RecordNumber in case of wrap-around, */
			/* we look for using '=' */
			if (0 != timestamp || (DWORD)FirstID == ((PEVENTLOGRECORD)pELR)->RecordNumber)
			{
				const char	*str_severity;
				unsigned short	severity;
				unsigned long	logeventid;
				char		*value, str_logeventid[8];

				/* increase counter only for records >= FirstID (start point for the search) */
				/* to avoid wrap-around of the 32b RecordNumber we increase the 64b lastlogsize */
				if (0 == timestamp)
					lastlogsize = FirstID;
				else
					lastlogsize += 1;

				zbx_parse_eventlog_message(eventlog_name_w, (EVENTLOGRECORD *)pELR, &source, &value,
						&severity, &timestamp, &logeventid);

				switch (severity)
				{
					case EVENTLOG_SUCCESS:
					case EVENTLOG_INFORMATION_TYPE:
						severity = ITEM_LOGTYPE_INFORMATION;
						str_severity = INFORMATION_TYPE;
						break;
					case EVENTLOG_WARNING_TYPE:
						severity = ITEM_LOGTYPE_WARNING;
						str_severity = WARNING_TYPE;
						break;
					case EVENTLOG_ERROR_TYPE:
						severity = ITEM_LOGTYPE_ERROR;
						str_severity = ERROR_TYPE;
						break;
					case EVENTLOG_AUDIT_FAILURE:
						severity = ITEM_LOGTYPE_FAILURE_AUDIT;
						str_severity = AUDIT_FAILURE;
						break;
					case EVENTLOG_AUDIT_SUCCESS:
						severity = ITEM_LOGTYPE_SUCCESS_AUDIT;
						str_severity = AUDIT_SUCCESS;
						break;
					default:
						*error = zbx_dsprintf(*error, "Invalid severity detected: '%hu'.",
								severity);
						goto out;
				}

				zbx_snprintf(str_logeventid, sizeof(str_logeventid), "%lu", logeventid);

				if (0 == p_count)
				{
					int	ret1 = ZBX_REGEXP_NO_MATCH, ret2 = ZBX_REGEXP_NO_MATCH,
						ret3 = ZBX_REGEXP_NO_MATCH, ret4 = ZBX_REGEXP_NO_MATCH;

					if (FAIL == (ret1 = zbx_regexp_match_ex(regexps, value, pattern,
							ZBX_CASE_SENSITIVE)))
					{
						*error = zbx_strdup(*error,
								"Invalid regular expression in the second parameter.");
						match = FAIL;
					}
					else if (FAIL == (ret2 = zbx_regexp_match_ex(regexps, str_severity,
							key_severity, ZBX_IGNORE_CASE)))
					{
						*error = zbx_strdup(*error,
								"Invalid regular expression in the third parameter.");
						match = FAIL;
					}
					else if (FAIL == (ret3 = zbx_regexp_match_ex(regexps, source, key_source,
							ZBX_IGNORE_CASE)))
					{
						*error = zbx_strdup(*error,
								"Invalid regular expression in the fourth parameter.");
						match = FAIL;
					}
					else if (FAIL == (ret4 = zbx_regexp_match_ex(regexps, str_logeventid,
							key_logeventid, ZBX_CASE_SENSITIVE)))
					{
						*error = zbx_strdup(*error,
								"Invalid regular expression in the fifth parameter.");
						match = FAIL;
					}

					if (FAIL == match)
					{
						zbx_free(source);
						zbx_free(value);

						ret = FAIL;
						break;
					}
					else
					{
						match = ZBX_REGEXP_MATCH == ret1 && ZBX_REGEXP_MATCH == ret2 &&
								ZBX_REGEXP_MATCH == ret3 && ZBX_REGEXP_MATCH == ret4;
					}
				}
				else
				{
					match = ZBX_REGEXP_MATCH == zbx_regexp_match_ex(regexps, value, pattern,
								ZBX_CASE_SENSITIVE) &&
							ZBX_REGEXP_MATCH == zbx_regexp_match_ex(regexps, str_severity,
								key_severity, ZBX_IGNORE_CASE) &&
							ZBX_REGEXP_MATCH == zbx_regexp_match_ex(regexps, source,
								key_source, ZBX_IGNORE_CASE) &&
							ZBX_REGEXP_MATCH == zbx_regexp_match_ex(regexps,
								str_logeventid, key_logeventid, ZBX_CASE_SENSITIVE);
				}

				if (1 == match)
				{
					if (EVT_LOG_ITEM == evt_item_type)
					{
						send_err = process_value_cb(addrs, agent2_result, metric->itemid, config_hostname,
								metric->key, value, ITEM_STATE_NORMAL,
								&lastlogsize, NULL, &timestamp, source, &severity,
								&logeventid, metric->flags | ZBX_METRIC_FLAG_PERSISTENT,
								config_tls, config_timeout, config_source_ip, config_buffer_send,
								config_buffer_size);

						if (SUCCEED == send_err)
						{
							*lastlogsize_sent = lastlogsize;
							s_count++;
						}
					}
					else
						s_count++;
				}
				p_count++;

				zbx_free(source);
				zbx_free(value);

				if (EVT_LOG_ITEM == evt_item_type)
				{
					if (SUCCEED == send_err)
					{
						metric->lastlogsize = lastlogsize;
					}
					else
					{
						/* buffer is full, stop processing active checks */
						/* till the buffer is cleared */
						break;
					}
				}

				if (0 >= (delay = metric->nextcheck - (int)time(NULL)))
					delay = 1;

				/* do not flood Zabbix server if file grows too fast */
				if (s_count >= (rate * delay))
					break;

				/* do not flood local system if file grows too fast */
				if (p_count >= (4 * rate * delay))
					break;
			}

			pELR += ((PEVENTLOGRECORD)pELR)->Length;
		}

		if (pELR < pEndOfRecords)
			error_code = ERROR_NO_MORE_ITEMS;
	}
finish:
	ret = SUCCEED;

	if (EVT_LOG_COUNT_ITEM == evt_item_type)
	{
		char	buf[ZBX_MAX_UINT64_LEN];

		zbx_snprintf(buf, sizeof(buf), "%d", s_count);
		send_err = process_value_cb(addrs, agent2_result, metric->itemid, config_hostname, metric->key, buf,
				ITEM_STATE_NORMAL, &lastlogsize, NULL, NULL, NULL, NULL, NULL, metric->flags |
				ZBX_METRIC_FLAG_PERSISTENT, config_tls, config_timeout, config_source_ip,
				config_buffer_send, config_buffer_size);

		if (SUCCEED == send_err)
		{
			*lastlogsize_sent = lastlogsize;
			metric->lastlogsize = lastlogsize;
		}
	}
out:
	zbx_close_eventlog(eventlog_handle);
	zbx_free(eventlog_name_w);
	zbx_free(pELRs);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
#undef EVT_LOG_COUNT_ITEM
#undef EVT_LOG_ITEM
}
