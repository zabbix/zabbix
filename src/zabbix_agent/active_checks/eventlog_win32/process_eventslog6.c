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

#include "process_eventslog6.h"
#include "severity_constants.h"

#include "../../metrics/metrics.h"
#include "../../logfiles/logfiles.h"

#include "zbxregexp.h"
#include "zbxstr.h"
#include "zbx_item_constants.h"
#include "zbxalgo.h"
#include "zbxlog.h"

#include "winmeta.h"

#include <sddl.h> /* ConvertSidToStringSid */

/* winevt.h contents START */
typedef struct _EVT_VARIANT
{
	union
	{
		BOOL		BooleanVal;
		INT8		SByteVal;
		INT16		Int16Val;
		INT32		Int32Val;
		INT64		Int64Val;
		UINT8		ByteVal;
		UINT16		UInt16Val;
		UINT32		UInt32Val;
		UINT64		UInt64Val;
		float		SingleVal;
		double		DoubleVal;
		ULONGLONG	FileTimeVal;
		SYSTEMTIME	*SysTimeVal;
		GUID		*GuidVal;
		const wchar_t	*StringVal;
		const char	*AnsiStringVal;
		PBYTE		BinaryVal;
		PSID		SidVal;
		size_t		SizeTVal;

		/* array fields */
		BOOL		*BooleanArr;
		INT8		*SByteArr;
		INT16		*Int16Arr;
		INT32		*Int32Arr;
		INT64		*Int64Arr;
		UINT8		*ByteArr;
		UINT16		*UInt16Arr;
		UINT32		*UInt32Arr;
		UINT64		*UInt64Arr;
		float		*SingleArr;
		double		*DoubleArr;
		FILETIME	*FileTimeArr;
		SYSTEMTIME	*SysTimeArr;
		GUID		*GuidArr;
		wchar_t		**StringArr;
		char		**AnsiStringArr;
		PSID		*SidArr;
		size_t		*SizeTArr;

		/* internal fields */
		EVT_HANDLE	EvtHandleVal;
		const wchar_t	*XmlVal;
		const wchar_t	**XmlValArr;
	};

	DWORD	Count;   /* number of elements (not length) in bytes */
	DWORD	Type;
}
EVT_VARIANT, *PEVT_VARIANT;

typedef enum _EVT_RENDER_CONTEXT_FLAGS
{
	EvtRenderContextValues = 0,	/* render specific properties */
	EvtRenderContextSystem,		/* render all system properties (System) */
	EvtRenderContextUser		/* render all user properties (User/EventData) */
}
EVT_RENDER_CONTEXT_FLAGS;

typedef enum _EVT_QUERY_FLAGS
{
	EvtQueryChannelPath = 0x1,
	EvtQueryFilePath = 0x2,
	EvtQueryForwardDirection = 0x100,
	EvtQueryReverseDirection = 0x200,
	EvtQueryTolerateQueryErrors = 0x1000
}
EVT_QUERY_FLAGS;

typedef enum _EVT_RENDER_FLAGS
{
	EvtRenderEventValues = 0,	/* variants */
	EvtRenderEventXml,		/* XML */
	EvtRenderBookmark		/* bookmark */
}
EVT_RENDER_FLAGS;

typedef enum _EVT_FORMAT_MESSAGE_FLAGS
{
	EvtFormatMessageEvent = 1,
	EvtFormatMessageLevel,
	EvtFormatMessageTask,
	EvtFormatMessageOpcode,
	EvtFormatMessageKeyword,
	EvtFormatMessageChannel,
	EvtFormatMessageProvider,
	EvtFormatMessageId,
	EvtFormatMessageXml,
}
EVT_FORMAT_MESSAGE_FLAGS;

typedef enum _EVT_VARIANT_TYPE
{
	EvtVarTypeNull = 0,
	EvtVarTypeString = 1,
	EvtVarTypeAnsiString = 2,
	EvtVarTypeSByte = 3,
	EvtVarTypeByte = 4,
	EvtVarTypeInt16 = 5,
	EvtVarTypeUInt16 = 6,
	EvtVarTypeInt32 = 7,
	EvtVarTypeUInt32 = 8,
	EvtVarTypeInt64 = 9,
	EvtVarTypeUInt64 = 10,
	EvtVarTypeSingle = 11,
	EvtVarTypeDouble = 12,
	EvtVarTypeBoolean = 13,
	EvtVarTypeBinary = 14,
	EvtVarTypeGuid = 15,
	EvtVarTypeSizeT = 16,
	EvtVarTypeFileTime = 17,
	EvtVarTypeSysTime = 18,
	EvtVarTypeSid = 19,
	EvtVarTypeHexInt32 = 20,
	EvtVarTypeHexInt64 = 21,

	/* these types used internally */
	EvtVarTypeEvtHandle = 32,
	EvtVarTypeEvtXml = 35
}
EVT_VARIANT_TYPE;

EVT_HANDLE WINAPI	EvtCreateRenderContext(DWORD ValuePathsCount, const wchar_t **ValuePaths, DWORD Flags);
EVT_HANDLE WINAPI	EvtQuery(EVT_HANDLE Session, const wchar_t *Path, const wchar_t *Query, DWORD Flags);
EVT_HANDLE WINAPI	EvtOpenPublisherMetadata(EVT_HANDLE Session, const wchar_t *PublisherId,
			const wchar_t *LogFilePath, LCID Locale, DWORD Flags);
BOOL WINAPI		EvtRender(EVT_HANDLE Context, EVT_HANDLE Fragment, DWORD Flags, DWORD BufferSize,
			PVOID Buffer, PDWORD BufferUsed, PDWORD PropertyCount);
BOOL WINAPI		EvtNext(EVT_HANDLE ResultSet, DWORD EventsSize, PEVT_HANDLE Events, DWORD Timeout, DWORD Flags,
			__out PDWORD Returned);
BOOL WINAPI		EvtClose(EVT_HANDLE Object);
BOOL WINAPI		EvtFormatMessage(EVT_HANDLE PublisherMetadata, EVT_HANDLE Event, DWORD MessageId,
			DWORD ValueCount, PEVT_VARIANT Values, DWORD Flags, DWORD BufferSize, wchar_t *Buffer,
			PDWORD BufferUsed);
/* winevt.h contents END */

#define	VAR_PROVIDER_NAME(p)			(p[0].StringVal)
#define	VAR_SOURCE_NAME(p)			(p[1].StringVal)
#define	VAR_RECORD_NUMBER(p)			(p[2].UInt64Val)
#define	VAR_EVENT_ID(p)				(p[3].UInt16Val)
#define	VAR_LEVEL(p)				(p[4].ByteVal)
#define	VAR_KEYWORDS(p)				(p[5].UInt64Val)
#define	VAR_TIME_CREATED(p)			(p[6].FileTimeVal)
#define	VAR_EVENT_DATA_STRING(p)		(p[7].StringVal)
#define	VAR_EVENT_DATA_STRING_ARRAY(p, i)	(p[7].StringArr[i])
#define	VAR_EVENT_DATA_TYPE(p)			(p[7].Type)
#define	VAR_EVENT_DATA_COUNT(p)			(p[7].Count)

ZBX_VECTOR_IMPL(prov_meta, provider_meta_t)

/* gets handles of Event Log */
static int	zbx_get_handle_eventlog6(const wchar_t *wsource, zbx_uint64_t *lastlogsize, EVT_HANDLE *query,
		char **error)
{
	wchar_t	*event_query = NULL;
	char	*tmp_str = NULL;
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(), previous lastlogsize:" ZBX_FS_UI64, __func__, *lastlogsize);

	/* start building the query */
	tmp_str = zbx_dsprintf(NULL, "Event/System[EventRecordID>" ZBX_FS_UI64 "]", *lastlogsize);
	event_query = zbx_utf8_to_unicode(tmp_str);

	/* create massive query for an event on a local computer*/
	*query = EvtQuery(NULL, wsource, event_query, EvtQueryChannelPath);
	if (NULL == *query)
	{
		DWORD	status;

		if (ERROR_EVT_CHANNEL_NOT_FOUND == (status = GetLastError()))
			*error = zbx_dsprintf(*error, "EvtQuery channel missed:%s", zbx_strerror_from_system(status));
		else
			*error = zbx_dsprintf(*error, "EvtQuery failed:%s", zbx_strerror_from_system(status));

		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(tmp_str);
	zbx_free(event_query);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	get_eventlog6_id(EVT_HANDLE *event_query, EVT_HANDLE *render_context, zbx_uint64_t *id, char **error)
{
	int		ret = FAIL;
	DWORD		size_required_next = 0, size_required = 0, size = 0, status = 0, bookmarkedCount = 0;
	EVT_VARIANT	*renderedContent = NULL;
	EVT_HANDLE	event_bookmark = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (TRUE != EvtNext(*event_query, 1, &event_bookmark, INFINITE, 0, &size_required_next))
	{
		/* no data in eventlog */
		zabbix_log(LOG_LEVEL_DEBUG, "%s() EvtNext failed:%s", __func__,
				zbx_strerror_from_system(GetLastError()));
		*id = 0;
		ret = SUCCEED;
		goto out;
	}

	/* obtain the information from selected events */
	if (TRUE != EvtRender(*render_context, event_bookmark, EvtRenderEventValues, size, renderedContent,
			&size_required, &bookmarkedCount))
	{
		/* information exceeds the allocated space */
		if (ERROR_INSUFFICIENT_BUFFER != (status = GetLastError()))
		{
			*error = zbx_dsprintf(*error, "EvtRender failed:%s", zbx_strerror_from_system(status));
			goto out;
		}

		size = size_required;
		renderedContent = (EVT_VARIANT*)zbx_malloc(NULL, size);

		if (TRUE != EvtRender(*render_context, event_bookmark, EvtRenderEventValues, size, renderedContent,
				&size_required, &bookmarkedCount))
		{
			*error = zbx_dsprintf(*error, "EvtRender failed:%s", zbx_strerror_from_system(GetLastError()));
			goto out;
		}
	}

	*id = VAR_RECORD_NUMBER(renderedContent);
	ret = SUCCEED;
out:
	if (NULL != event_bookmark)
		EvtClose(event_bookmark);

	zbx_free(renderedContent);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s id:" ZBX_FS_UI64, __func__, zbx_result_string(ret), id);

	return ret;
}

/* opens Event Log using API 6 and returns number of records */
static int	zbx_open_eventlog6(const wchar_t *wsource, zbx_uint64_t *lastlogsize, EVT_HANDLE *render_context,
		zbx_uint64_t *FirstID, zbx_uint64_t *LastID, char **error)
{
	const wchar_t	*RENDER_ITEMS[] = {
		L"/Event/System/Provider/@Name",
		L"/Event/System/Provider/@EventSourceName",
		L"/Event/System/EventRecordID",
		L"/Event/System/EventID",
		L"/Event/System/Level",
		L"/Event/System/Keywords",
		L"/Event/System/TimeCreated/@SystemTime",
		L"/Event/EventData/Data"
	};
#define	RENDER_ITEMS_COUNT (sizeof(RENDER_ITEMS) / sizeof(const wchar_t *))
	EVT_HANDLE	tmp_first_event_query = NULL, tmp_last_event_query = NULL;
	DWORD		status = 0;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastlogsize:" ZBX_FS_UI64, __func__, *lastlogsize);

	*FirstID = 0;
	*LastID = 0;

	/* get the number of the oldest record in the log				*/
	/* "EvtGetLogInfo()" does not work properly with "EvtLogOldestRecordNumber"	*/
	/* we have to get it from the first EventRecordID				*/

	/* create the system render */
	if (NULL == (*render_context = EvtCreateRenderContext(RENDER_ITEMS_COUNT, RENDER_ITEMS,
			EvtRenderContextValues)))
	{
		*error = zbx_dsprintf(*error, "EvtCreateRenderContext failed:%s",
				zbx_strerror_from_system(GetLastError()));
		goto out;
	}
	/* get all eventlog */
	if (NULL == (tmp_first_event_query = EvtQuery(NULL, wsource, NULL, EvtQueryChannelPath)))
	{
		if (ERROR_EVT_CHANNEL_NOT_FOUND == (status = GetLastError()))
			*error = zbx_dsprintf(*error, "EvtQuery channel missed:%s", zbx_strerror_from_system(status));
		else
			*error = zbx_dsprintf(*error, "EvtQuery failed:%s", zbx_strerror_from_system(status));

		goto out;
	}

	if (SUCCEED != get_eventlog6_id(&tmp_first_event_query, render_context, FirstID, error))
		goto out;

	if (0 == *FirstID)
	{
		/* no data in eventlog */
		zabbix_log(LOG_LEVEL_DEBUG, "%s() first EvtNext failed", __func__);
		*FirstID = 1;
		*LastID = 1;
		*lastlogsize = 0;
		ret = SUCCEED;
		goto out;
	}

	if (NULL == (tmp_last_event_query = EvtQuery(NULL, wsource, NULL,
			EvtQueryChannelPath | EvtQueryReverseDirection)))
	{
		if (ERROR_EVT_CHANNEL_NOT_FOUND == (status = GetLastError()))
			*error = zbx_dsprintf(*error, "EvtQuery channel missed:%s", zbx_strerror_from_system(status));
		else
			*error = zbx_dsprintf(*error, "EvtQuery failed:%s", zbx_strerror_from_system(status));

		goto out;
	}

	if (SUCCEED != get_eventlog6_id(&tmp_last_event_query, render_context, LastID, error) || 0 == *LastID)
	{
		/* no data in eventlog */
		zabbix_log(LOG_LEVEL_DEBUG, "%s() last EvtNext failed", __func__);
		*LastID = 1;
	}
	else
		*LastID += 1;	/* we should read the last record */

	if (*lastlogsize >= *LastID)
	{
		*lastlogsize = *FirstID - 1;
		zabbix_log(LOG_LEVEL_WARNING, "lastlogsize is too big. It is set to:" ZBX_FS_UI64, *lastlogsize);
	}

	ret = SUCCEED;
out:
	if (NULL != tmp_first_event_query)
		EvtClose(tmp_first_event_query);
	if (NULL != tmp_last_event_query)
		EvtClose(tmp_last_event_query);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s FirstID:" ZBX_FS_UI64 " LastID:" ZBX_FS_UI64,
			__func__, zbx_result_string(ret), *FirstID, *LastID);

	return ret;
#undef	RENDER_ITEMS_COUNT
}

/* finalize eventlog6 and free the handles */
int	finalize_eventlog6(EVT_HANDLE *render_context, EVT_HANDLE *query)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL != *query)
	{
		EvtClose(*query);
		*query = NULL;
	}

	if (NULL != *render_context)
	{
		EvtClose(*render_context);
		*render_context = NULL;
	}

	ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/* initializes Event Logs with Windows API version 6 */
int	initialize_eventlog6(const char *source, zbx_uint64_t *lastlogsize, zbx_uint64_t *FirstID,
		zbx_uint64_t *LastID, EVT_HANDLE *render_context, EVT_HANDLE *query, char **error)
{
	wchar_t	*wsource = NULL;
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() source:'%s' previous lastlogsize:" ZBX_FS_UI64,
			__func__, source, *lastlogsize);

	if (NULL == source || '\0' == *source)
	{
		*error = zbx_dsprintf(*error, "cannot open eventlog with empty name.");
		goto out;
	}

	wsource = zbx_utf8_to_unicode(source);

	if (SUCCEED != zbx_open_eventlog6(wsource, lastlogsize, render_context, FirstID, LastID, error))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot open eventlog '%s'", source);
		goto out;
	}

	if (SUCCEED != zbx_get_handle_eventlog6(wsource, lastlogsize, query, error))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot get eventlog handle '%s'", source);
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(wsource);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static EVT_HANDLE	open_publisher_metadata(const wchar_t *pname, const char* utf8_name)
{
	EVT_HANDLE handle;

	if (NULL == (handle = EvtOpenPublisherMetadata(NULL, pname, NULL, 0, 0)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "provider '%s' could not be opened: %s", utf8_name,
				zbx_strerror_from_system(GetLastError()));
	}

	return handle;
}

static int	get_publisher_metadata(zbx_vector_prov_meta_t *prov_meta, const wchar_t *pname, int force_fetch,
		EVT_HANDLE *dest)
{
	char		*tmp_pname = zbx_unicode_to_utf8(pname);
	int		index, ret = FAIL;
	provider_meta_t	p_meta;

	p_meta.name = tmp_pname;

	if (FAIL == (index = zbx_vector_prov_meta_bsearch((const zbx_vector_prov_meta_t *)prov_meta,
			p_meta, ZBX_DEFAULT_STR_COMPARE_FUNC)))
	{
		if (NULL == (*dest = open_publisher_metadata(pname, tmp_pname)))
			goto out;

		p_meta.name = zbx_strdup(NULL, tmp_pname);
		p_meta.handle = *dest;

		index = zbx_vector_prov_meta_nearestindex(prov_meta, p_meta, ZBX_DEFAULT_STR_COMPARE_FUNC);
		zbx_vector_prov_meta_insert(prov_meta, p_meta, index);

		ret = SUCCEED;
	}
	else {
		if (1 == force_fetch)
		{
			if (NULL == (*dest = open_publisher_metadata(pname, tmp_pname)))
				goto out;

			prov_meta->values[index].handle = *dest;
		}
		else
			*dest = prov_meta->values[index].handle;

		ret = SUCCEED;
	}
out:
	zbx_free(tmp_pname);

	return ret;
}

/* expands string message from specific event handler */
static char	*expand_message6(const wchar_t *pname, EVT_HANDLE event, zbx_vector_prov_meta_t *prov_meta)
{
	wchar_t		*pmessage = NULL;
	EVT_HANDLE	provider = NULL;
	DWORD		require = 0;
	char		*out_message = NULL;
	int		refetch_done = 0;
	DWORD		error;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == get_publisher_metadata(prov_meta, pname, 0, &provider))
		goto err;

	while (1)
	{
		if (TRUE != EvtFormatMessage(provider, event, 0, 0, NULL, EvtFormatMessageEvent, 0, NULL, &require))
		{
			int	last_err = GetLastError();

			if (ERROR_INSUFFICIENT_BUFFER == last_err)
			{
				error = ERROR_SUCCESS;

				pmessage = zbx_malloc(pmessage, sizeof(WCHAR) * require);

				if (TRUE != EvtFormatMessage(provider, event, 0, 0, NULL, EvtFormatMessageEvent,
						require, pmessage, &require))
				{
					error = GetLastError();
				}

				if (ERROR_SUCCESS == error || ERROR_EVT_UNRESOLVED_VALUE_INSERT == error ||
						ERROR_EVT_UNRESOLVED_PARAMETER_INSERT == error ||
						ERROR_EVT_MAX_INSERTS_REACHED == error)
				{
					out_message = zbx_unicode_to_utf8(pmessage);
					goto out;
				}
				else
					break;
			}
			else if (ERROR_INVALID_HANDLE == last_err)
			{
				if (1 == refetch_done)
					break;

				refetch_done = 1;

				if (FAIL == get_publisher_metadata(prov_meta, pname, 1, &provider))
					break;
			}
			else
				break;
		}
		else
			goto out;
	}
err:
	zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot format message: %s", __func__, zbx_strerror_from_system(error));
out:
	zbx_free(pmessage);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, out_message);

	/* should be freed */
	return out_message;
}

static void	replace_sid_to_account(PSID sidVal, char **out_message)
{
#define MAX_NAME			256
	DWORD	nlen = MAX_NAME, dlen = MAX_NAME;
	wchar_t	name[MAX_NAME], dom[MAX_NAME], *sid = NULL;
	int	iUse;
	char	userName[MAX_NAME * 4], domName[MAX_NAME * 4], sidName[MAX_NAME * 4], *tmp, buffer[MAX_NAME * 8];
#undef MAX_NAME
	if (0 == LookupAccountSid(NULL, sidVal, name, &nlen, dom, &dlen, (PSID_NAME_USE)&iUse))
	{
		/* don't replace security ID if no mapping between account names and security IDs was done */
		zabbix_log(LOG_LEVEL_DEBUG, "LookupAccountSid failed:%s", zbx_strerror_from_system(GetLastError()));
		return;
	}

	if (0 == nlen)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "LookupAccountSid returned empty user name");
		return;
	}

	if (0 == ConvertSidToStringSid(sidVal, &sid))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "ConvertSidToStringSid failed:%s",
				zbx_strerror_from_system(GetLastError()));
		return;
	}

	zbx_unicode_to_utf8_static(sid, sidName, sizeof(sidName));
	zbx_unicode_to_utf8_static(name, userName, sizeof(userName));

	if (0 != dlen)
	{
		zbx_unicode_to_utf8_static(dom, domName, sizeof(domName));
		zbx_snprintf(buffer, sizeof(buffer), "%s\\%s", domName, userName);
	}
	else
		zbx_strlcpy(buffer, userName, sizeof(buffer));	/* NULL SID */

	tmp = *out_message;
	*out_message = zbx_string_replace(*out_message, sidName, buffer);

	LocalFree(sid);
	zbx_free(tmp);
}

static void	replace_sids_to_accounts(EVT_HANDLE event_bookmark, char **out_message)
{
	DWORD		status, dwBufferSize = 0, dwBufferUsed = 0, dwPropertyCount = 0, i;
	PEVT_VARIANT	renderedContent = NULL;
	EVT_HANDLE	render_context;

	if (NULL == (render_context = EvtCreateRenderContext(0, NULL, EvtRenderContextUser)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "EvtCreateRenderContext failed:%s",
				zbx_strerror_from_system(GetLastError()));
		goto cleanup;
	}

	if (TRUE != EvtRender(render_context, event_bookmark, EvtRenderEventValues, dwBufferSize, renderedContent,
			&dwBufferUsed, &dwPropertyCount))
	{
		if (ERROR_INSUFFICIENT_BUFFER != (status = GetLastError()))
		{
			zabbix_log(LOG_LEVEL_WARNING, "EvtRender failed:%s", zbx_strerror_from_system(status));
			goto cleanup;
		}

		dwBufferSize = dwBufferUsed;
		renderedContent = (PEVT_VARIANT)zbx_malloc(NULL, dwBufferSize);

		if (TRUE != EvtRender(render_context, event_bookmark, EvtRenderEventValues, dwBufferSize,
				renderedContent, &dwBufferUsed, &dwPropertyCount))
		{
			zabbix_log(LOG_LEVEL_WARNING, "EvtRender failed:%s", zbx_strerror_from_system(GetLastError()));
			goto cleanup;
		}
	}

	for (i = 0; i < dwPropertyCount; i++)
	{
		if (EvtVarTypeSid == renderedContent[i].Type)
			replace_sid_to_account(renderedContent[i].SidVal, out_message);
	}
cleanup:
	if (NULL != render_context)
		EvtClose(render_context);

	zbx_free(renderedContent);
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses single Event Log record                                    *
 *                                                                            *
 * Parameters: wsource        - [IN] Event Log file name                      *
 *             render_context - [IN] handle to rendering context              *
 *             event_bookmark - [IN/OUT] handle of Event record for parsing   *
 *             which          - [IN/OUT] position of Event Log record         *
 *             ...            - [OUT] ELR detail                              *
 *             prov_meta      - [IN/OUT] provider metadata cache              *
 *             error          - [OUT] error message in case of failure        *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	zbx_parse_eventlog_message6(const wchar_t *wsource, EVT_HANDLE *render_context,
		EVT_HANDLE *event_bookmark, zbx_uint64_t *which, unsigned short *out_severity,
		unsigned long *out_timestamp, char **out_provider, char **out_source, char **out_message,
		unsigned long *out_eventid, zbx_uint64_t *out_keywords, zbx_vector_prov_meta_t *prov_meta,
		int gather_evt_msg, char **error)
{
#define EVT_VARIANT_TYPE_ARRAY	128
#define EVT_VARIANT_TYPE_MASK	0x7f
	EVT_VARIANT*		renderedContent = NULL;
	const wchar_t		*pprovider = NULL;
	char			*tmp_str = NULL;
	DWORD			size = 0, bookmarkedCount = 0, require = 0, error_code;
	const zbx_uint64_t	sec_1970 = 116444736000000000, success_audit = 0x20000000000000,
				failure_audit = 0x10000000000000;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() EventRecordID:" ZBX_FS_UI64, __func__, *which);

	/* obtain the information from the selected events */
	if (TRUE != EvtRender(*render_context, *event_bookmark, EvtRenderEventValues, size, renderedContent,
			&require, &bookmarkedCount))
	{
		/* information exceeds the space allocated */
		if (ERROR_INSUFFICIENT_BUFFER != (error_code = GetLastError()))
		{
			*error = zbx_dsprintf(*error, "EvtRender failed: %s", zbx_strerror_from_system(error_code));
			goto out;
		}

		size = require;
		renderedContent = (EVT_VARIANT *)zbx_malloc(NULL, size);

		if (TRUE != EvtRender(*render_context, *event_bookmark, EvtRenderEventValues, size, renderedContent,
				&require, &bookmarkedCount))
		{
			*error = zbx_dsprintf(*error, "EvtRender failed: %s", zbx_strerror_from_system(GetLastError()));
			goto out;
		}
	}

	pprovider = VAR_PROVIDER_NAME(renderedContent);
	*out_provider = zbx_unicode_to_utf8(pprovider);
	*out_source = NULL;

	if (NULL != VAR_SOURCE_NAME(renderedContent))
	{
		*out_source = zbx_unicode_to_utf8(VAR_SOURCE_NAME(renderedContent));
	}

	*out_keywords = VAR_KEYWORDS(renderedContent) & (success_audit | failure_audit);
	*out_severity = VAR_LEVEL(renderedContent);
	*out_timestamp = (unsigned long)((VAR_TIME_CREATED(renderedContent) - sec_1970) / 10000000);
	*out_eventid = VAR_EVENT_ID(renderedContent);

	if (1 == gather_evt_msg)
		*out_message = expand_message6(pprovider, *event_bookmark, prov_meta);
	else
		*out_message = zbx_strdup(NULL, "");

	if (NULL != *out_message)
		replace_sids_to_accounts(*event_bookmark, out_message);

	tmp_str = zbx_unicode_to_utf8(wsource);

	if (VAR_RECORD_NUMBER(renderedContent) != *which)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() Overwriting expected EventRecordID:" ZBX_FS_UI64 " with the real"
				" EventRecordID:" ZBX_FS_UI64 " in eventlog '%s'", __func__, *which,
				VAR_RECORD_NUMBER(renderedContent), tmp_str);
		*which = VAR_RECORD_NUMBER(renderedContent);
	}

	/* some events don't have enough information for making event message */
	if (NULL == *out_message)
	{
		*out_message = zbx_strdcatf(*out_message, "The description for Event ID:%lu in Source:'%s'"
				" cannot be found. Either the component that raises this event is not installed"
				" on your local computer or the installation is corrupted. You can install or repair"
				" the component on the local computer. If the event originated on another computer,"
				" the display information had to be saved with the event.", *out_eventid,
				NULL == *out_provider ? "" : *out_provider);
		if (EvtVarTypeString == (VAR_EVENT_DATA_TYPE(renderedContent) & EVT_VARIANT_TYPE_MASK))
		{
			unsigned int	i;
			char		*data = NULL;

			if (0 != (VAR_EVENT_DATA_TYPE(renderedContent) & EVT_VARIANT_TYPE_ARRAY) &&
				0 < VAR_EVENT_DATA_COUNT(renderedContent))
			{
				*out_message = zbx_strdcatf(*out_message, " The following information was included"
						" with the event: ");

				for (i = 0; i < VAR_EVENT_DATA_COUNT(renderedContent); i++)
				{
					if (NULL != VAR_EVENT_DATA_STRING_ARRAY(renderedContent, i))
					{
						if (0 < i)
							*out_message = zbx_strdcat(*out_message, "; ");

						data = zbx_unicode_to_utf8(VAR_EVENT_DATA_STRING_ARRAY(renderedContent,
								i));
						*out_message = zbx_strdcatf(*out_message, "%s", data);
						zbx_free(data);
					}
				}
			}
			else if (NULL != VAR_EVENT_DATA_STRING(renderedContent))
			{
				data = zbx_unicode_to_utf8(VAR_EVENT_DATA_STRING(renderedContent));
				*out_message = zbx_strdcatf(*out_message, "The following information was included"
						" with the event: %s", data);
				zbx_free(data);
			}
		}
	}

	ret = SUCCEED;
out:
	EvtClose(*event_bookmark);
	*event_bookmark = NULL;

	zbx_free(tmp_str);
	zbx_free(renderedContent);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
#undef EVT_VARIANT_TYPE_ARRAY
#undef EVT_VARIANT_TYPE_MASK
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
 *             render_context     - [IN] handle to rendering context            *
 *             query              - [IN] handle to query results                *
 *             lastlogsize        - [IN] position of last processed record      *
 *             FirstID            - [IN] first record in Event Log file         *
 *             LastID             - [IN] last record in Event Log file          *
 *             regexps            - [IN] set of regexp rules for Event Log test *
 *             pattern            - [IN] regular expression or global regular   *
 *                                       expression name (@<global regexp       *
 *                                       name>).                                *
 *             key_severity       - [IN] severity of logged data sources        *
 *             key_source         - [IN] name of logged data source             *
 *             key_logeventid     - [IN] application-specific identifier for    *
 *                                       event                                  *
 *             rate               - [IN] threshold of records count at time     *
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
 *             prov_meta          - [IN/OUT] provider metadata cache            *
 *             error              - [OUT] error message in case of failure      *
 *                                                                              *
 * Return value: SUCCEED or FAIL                                                *
 *                                                                              *
 ********************************************************************************/
int	process_eventslog6(zbx_vector_addr_ptr_t *addrs, zbx_vector_ptr_t *agent2_result,
		const char *eventlog_name, EVT_HANDLE *render_context, EVT_HANDLE *query, zbx_uint64_t lastlogsize,
		zbx_uint64_t FirstID, zbx_uint64_t LastID, zbx_vector_expression_t *regexps, const char *pattern,
		const char *key_severity, const char *key_source, const char *key_logeventid, int rate,
		zbx_process_value_func_t process_value_cb, const zbx_config_tls_t *config_tls, int config_timeout,
		const char *config_source_ip, const char *config_hostname, int config_buffer_send,
		int config_buffer_size, zbx_active_metric_t *metric, zbx_uint64_t *lastlogsize_sent,
		zbx_vector_prov_meta_t *prov_meta, char **error)
{
#define EVT_ARRAY_SIZE	100
#define EVT_LOG_ITEM 0
#define EVT_LOG_COUNT_ITEM 1
	const char	*str_severity;
	zbx_uint64_t	keywords, i, reading_startpoint = 0;
	wchar_t		*eventlog_name_w = NULL;
	int		s_count = 0, p_count = 0, send_err = SUCCEED, ret = FAIL, match = SUCCEED, evt_item_type;
	DWORD		required_buf_size = 0, error_code = ERROR_SUCCESS;

	unsigned long	evt_timestamp, evt_eventid = 0;
	char		*evt_provider, *evt_source, *evt_message, str_logeventid[8];
	unsigned short	evt_severity;
	EVT_HANDLE	event_bookmarks[EVT_ARRAY_SIZE];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() source: '%s' previous lastlogsize: " ZBX_FS_UI64 ", FirstID: "
			ZBX_FS_UI64 ", LastID: " ZBX_FS_UI64, __func__, eventlog_name, lastlogsize, FirstID,
			LastID);

	if (0 != (ZBX_METRIC_FLAG_LOG_COUNT & metric->flags))
		evt_item_type = EVT_LOG_COUNT_ITEM;
	else
		evt_item_type = EVT_LOG_ITEM;

	/* update counters */
	if (1 == metric->skip_old_data)
	{
		metric->lastlogsize = lastlogsize = LastID - 1;
		metric->skip_old_data = 0;
		zabbix_log(LOG_LEVEL_DEBUG, "skipping existing data: lastlogsize:" ZBX_FS_UI64, lastlogsize);
		goto finish;
	}

	if (NULL == *query)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() no EvtQuery handle", __func__);
		goto out;
	}

	if (lastlogsize >= FirstID && lastlogsize < LastID)
		reading_startpoint = lastlogsize + 1;
	else
		reading_startpoint = FirstID;

	if (reading_startpoint == LastID)	/* LastID = FirstID + count */
		goto finish;

	eventlog_name_w = zbx_utf8_to_unicode(eventlog_name);

	while (ERROR_SUCCESS == error_code)
	{
		/* get the entries */
		if (TRUE != EvtNext(*query, EVT_ARRAY_SIZE, event_bookmarks, INFINITE, 0, &required_buf_size))
		{
			/* The event reading query had less items than we calculated before. */
			/* Either the eventlog was cleaned or our calculations were wrong.   */
			/* Either way we can safely abort the query by setting NULL value    */
			/* and returning success, which is interpreted as empty eventlog.    */
			if (ERROR_NO_MORE_ITEMS == (error_code = GetLastError()))
				continue;

			*error = zbx_dsprintf(*error, "EvtNext failed: %s, EventRecordID:" ZBX_FS_UI64,
					zbx_strerror_from_system(error_code), lastlogsize + 1);
			goto out;
		}

		for (i = 0; i < required_buf_size; i++)
		{
			int	gather_evt_msg = 1, delay;

			if (EVT_LOG_COUNT_ITEM == evt_item_type && 1 > strlen(pattern))
				gather_evt_msg = 0;

			lastlogsize += 1;

			if (SUCCEED != zbx_parse_eventlog_message6(eventlog_name_w, render_context, &event_bookmarks[i],
					&lastlogsize, &evt_severity, &evt_timestamp, &evt_provider, &evt_source,
					&evt_message, &evt_eventid, &keywords, prov_meta, gather_evt_msg, error))
			{
				goto out;
			}

			switch (evt_severity)
			{
				case WINEVENT_LEVEL_LOG_ALWAYS:
				case WINEVENT_LEVEL_INFO:
					if (0 != (keywords & WINEVENT_KEYWORD_AUDIT_FAILURE))
					{
						evt_severity = ITEM_LOGTYPE_FAILURE_AUDIT;
						str_severity = AUDIT_FAILURE;
						break;
					}
					else if (0 != (keywords & WINEVENT_KEYWORD_AUDIT_SUCCESS))
					{
						evt_severity = ITEM_LOGTYPE_SUCCESS_AUDIT;
						str_severity = AUDIT_SUCCESS;
						break;
					}
					else
						evt_severity = ITEM_LOGTYPE_INFORMATION;
						str_severity = INFORMATION_TYPE;
						break;
				case WINEVENT_LEVEL_WARNING:
					evt_severity = ITEM_LOGTYPE_WARNING;
					str_severity = WARNING_TYPE;
					break;
				case WINEVENT_LEVEL_ERROR:
					evt_severity = ITEM_LOGTYPE_ERROR;
					str_severity = ERROR_TYPE;
					break;
				case WINEVENT_LEVEL_CRITICAL:
					evt_severity = ITEM_LOGTYPE_CRITICAL;
					str_severity = CRITICAL_TYPE;
					break;
				case WINEVENT_LEVEL_VERBOSE:
					evt_severity = ITEM_LOGTYPE_VERBOSE;
					str_severity = VERBOSE_TYPE;
					break;
				default:
					*error = zbx_dsprintf(*error, "Invalid severity detected: '%hu'.",
							evt_severity);
					goto out;
			}

			zbx_snprintf(str_logeventid, sizeof(str_logeventid), "%lu", evt_eventid);

			if (0 == p_count)
			{
				int	ret1 = ZBX_REGEXP_NO_MATCH, ret2 = ZBX_REGEXP_NO_MATCH,
					ret3 = ZBX_REGEXP_NO_MATCH, ret4 = ZBX_REGEXP_NO_MATCH;

				if (FAIL == (ret1 = zbx_regexp_match_ex(regexps, evt_message, pattern,
						ZBX_CASE_SENSITIVE)))
				{
					*error = zbx_strdup(*error,
							"Invalid regular expression in the second parameter.");
					match = FAIL;
				}
				else if (FAIL == (ret2 = zbx_regexp_match_ex(regexps, str_severity, key_severity,
						ZBX_IGNORE_CASE)))
				{
					*error = zbx_strdup(*error,
							"Invalid regular expression in the third parameter.");
					match = FAIL;
				}
				else if (FAIL == (ret3 = zbx_regexp_match_ex(regexps, evt_provider, key_source,
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
					zbx_free(evt_source);
					zbx_free(evt_provider);
					zbx_free(evt_message);

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
				match = ZBX_REGEXP_MATCH == zbx_regexp_match_ex(regexps, evt_message, pattern,
							ZBX_CASE_SENSITIVE) &&
						ZBX_REGEXP_MATCH == zbx_regexp_match_ex(regexps, str_severity,
							key_severity, ZBX_IGNORE_CASE) &&
						ZBX_REGEXP_MATCH == zbx_regexp_match_ex(regexps, evt_provider,
							key_source, ZBX_IGNORE_CASE) &&
						ZBX_REGEXP_MATCH == zbx_regexp_match_ex(regexps, str_logeventid,
							key_logeventid, ZBX_CASE_SENSITIVE);
			}

			if (1 == match)
			{
				if (EVT_LOG_ITEM == evt_item_type)
				{
					send_err = process_value_cb(addrs, agent2_result, metric->itemid, config_hostname,
							metric->key, evt_message, ITEM_STATE_NORMAL, &lastlogsize,
							NULL, &evt_timestamp, evt_provider, &evt_severity, &evt_eventid,
							metric->flags | ZBX_METRIC_FLAG_PERSISTENT, config_tls,
							config_timeout, config_source_ip, config_buffer_send,
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

			zbx_free(evt_source);
			zbx_free(evt_provider);
			zbx_free(evt_message);

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

		if (i < required_buf_size)
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
	for (i = 0; i < required_buf_size; i++)
	{
		if (NULL != event_bookmarks[i])
			EvtClose(event_bookmarks[i]);
	}

	zbx_free(eventlog_name_w);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s last eventid:%lu", __func__, zbx_result_string(ret), evt_eventid);

	return ret;
#undef EVT_ARRAY_SIZE
#undef EVT_LOG_COUNT_ITEM
#undef EVT_LOG_ITEM
}
