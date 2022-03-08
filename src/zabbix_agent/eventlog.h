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

#ifndef ZABBIX_EVENTLOG_H
#define ZABBIX_EVENTLOG_H

#include "config.h"
#include "logfiles/logfiles.h"

#if !defined(_WINDOWS) && !defined(__MINGW32__)
#	error "This module is only available for Windows OS"
#endif

#include "zbxalgo.h"
#include "active.h"
#include "metrics.h"

#define EVT_VARIANT_TYPE_ARRAY	128
#define EVT_VARIANT_TYPE_MASK	0x7f

/* Structures from winevt.h file */
typedef HANDLE EVT_HANDLE, *PEVT_HANDLE;

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

typedef enum	_EVT_LOG_PROPERTY_ID
{
	EvtLogCreationTime = 0,		/* EvtVarTypeFileTime */
	EvtLogLastAccessTime,		/* EvtVarTypeFileTime */
	EvtLogLastWriteTime,		/* EvtVarTypeFileTime */
	EvtLogFileSize,			/* EvtVarTypeUInt64 */
	EvtLogAttributes,		/* EvtVarTypeUInt32 */
	EvtLogNumberOfLogRecords,	/* EvtVarTypeUInt64 */
	EvtLogOldestRecordNumber,	/* EvtVarTypeUInt64 */
	EvtLogFull,			/* EvtVarTypeBoolean */
}
EVT_LOG_PROPERTY_ID;

typedef enum	_EVT_RENDER_CONTEXT_FLAGS
{
	EvtRenderContextValues = 0,	/* render specific properties */
	EvtRenderContextSystem,		/* render all system properties (System) */
	EvtRenderContextUser		/* render all user properties (User/EventData) */
}
EVT_RENDER_CONTEXT_FLAGS;

typedef enum	_EVT_QUERY_FLAGS
{
	EvtQueryChannelPath = 0x1,
	EvtQueryFilePath = 0x2,
	EvtQueryForwardDirection = 0x100,
	EvtQueryReverseDirection = 0x200,
	EvtQueryTolerateQueryErrors = 0x1000
}
EVT_QUERY_FLAGS;

typedef enum	_EVT_RENDER_FLAGS
{
	EvtRenderEventValues = 0,           /* variants */
	EvtRenderEventXml,                  /* XML */
	EvtRenderBookmark                   /* bookmark */
}
EVT_RENDER_FLAGS;

typedef enum	_EVT_FORMAT_MESSAGE_FLAGS
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

typedef enum	_EVT_OPEN_LOG_FLAGS
{
	EvtOpenChannelPath = 0x1,
	EvtOpenFilePath = 0x2
}
EVT_OPEN_LOG_FLAGS;

typedef enum	_EVT_VARIANT_TYPE
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

int			process_eventslog(zbx_vector_ptr_t *addrs, zbx_vector_ptr_t *agent2_result,
			const char *eventlog_name, zbx_vector_ptr_t *regexps, const char *pattern,
			const char *key_severity, const char *key_source, const char *key_logeventid,
			int rate, zbx_process_value_func_t process_value_cb, ZBX_ACTIVE_METRIC *metric,
			zbx_uint64_t *lastlogsize_sent, char **error);
int			process_eventslog6(zbx_vector_ptr_t *addrs, zbx_vector_ptr_t *agent2_result,
			const char *eventlog_name, EVT_HANDLE *render_context, EVT_HANDLE *query,
			zbx_uint64_t lastlogsize, zbx_uint64_t FirstID, zbx_uint64_t LastID,
			zbx_vector_ptr_t *regexps, const char *pattern, const char *key_severity, const char *key_source,
			const char *key_logeventid, int rate, zbx_process_value_func_t process_value_cb,
			ZBX_ACTIVE_METRIC *metric, zbx_uint64_t *lastlogsize_sent, char **error);
int			initialize_eventlog6(const char *source, zbx_uint64_t *lastlogsize, zbx_uint64_t *FirstID,
			zbx_uint64_t *LastID, EVT_HANDLE *render_context, EVT_HANDLE *query, char **error);
int			finalize_eventlog6(EVT_HANDLE *render_context, EVT_HANDLE *query);

EVT_HANDLE WINAPI	EvtOpenLog(EVT_HANDLE Session, const wchar_t *Path, DWORD Flags);
EVT_HANDLE WINAPI	EvtCreateRenderContext(DWORD ValuePathsCount, const wchar_t **ValuePaths, DWORD Flags);
EVT_HANDLE WINAPI	EvtQuery(EVT_HANDLE Session, const wchar_t *Path, const wchar_t *Query, DWORD Flags);
EVT_HANDLE WINAPI	EvtOpenPublisherMetadata(EVT_HANDLE Session, const wchar_t *PublisherId, const wchar_t *LogFilePath,
			LCID Locale, DWORD Flags);
BOOL WINAPI		EvtGetLogInfo( EVT_HANDLE Log, EVT_LOG_PROPERTY_ID PropertyId, DWORD PropertyValueBufferSize,
			PEVT_VARIANT PropertyValueBuffer,	__out PDWORD PropertyValueBufferUsed);
BOOL WINAPI		EvtRender(EVT_HANDLE Context, EVT_HANDLE Fragment, DWORD Flags, DWORD BufferSize,
			PVOID Buffer, PDWORD BufferUsed, PDWORD PropertyCount);
BOOL WINAPI		EvtNext(EVT_HANDLE ResultSet, DWORD EventsSize, PEVT_HANDLE Events, DWORD Timeout, DWORD Flags,
			__out PDWORD Returned);
BOOL WINAPI		EvtClose(EVT_HANDLE Object);
BOOL WINAPI		EvtFormatMessage(EVT_HANDLE PublisherMetadata, EVT_HANDLE Event, DWORD MessageId,
			DWORD ValueCount, PEVT_VARIANT Values, DWORD Flags, DWORD BufferSize, wchar_t *Buffer,
			PDWORD BufferUsed);

#endif	/* ZABBIX_EVENTLOG_H */
