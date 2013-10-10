/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

#ifndef _WINDOWS
#	error "This module is only available for Windows OS"
#endif

/* Structures from winevt.h file */
typedef HANDLE EVT_HANDLE, *PEVT_HANDLE;

typedef struct _EVT_VARIANT
{
    union
    {
        BOOL        BooleanVal;
        INT8        SByteVal;
        INT16       Int16Val;
        INT32       Int32Val;
        INT64       Int64Val;
        UINT8       ByteVal;
        UINT16      UInt16Val;
        UINT32      UInt32Val;
        UINT64      UInt64Val;
        float       SingleVal;
        double      DoubleVal;
        ULONGLONG   FileTimeVal;
        SYSTEMTIME* SysTimeVal;
        GUID*       GuidVal;
        LPCWSTR     StringVal;
        LPCSTR      AnsiStringVal;
        PBYTE       BinaryVal;
        PSID        SidVal;
        size_t      SizeTVal;

        // array fields
        BOOL*       BooleanArr;
        INT8*       SByteArr;
        INT16*      Int16Arr;
        INT32*      Int32Arr;
        INT64*      Int64Arr;
        UINT8*      ByteArr;
        UINT16*     UInt16Arr;
        UINT32*     UInt32Arr;
        UINT64*     UInt64Arr;
        float*      SingleArr;
        double*     DoubleArr;
        FILETIME*   FileTimeArr;
        SYSTEMTIME* SysTimeArr;
        GUID*       GuidArr;
        LPWSTR*     StringArr;
        LPSTR*      AnsiStringArr;
        PSID*       SidArr;
        size_t*     SizeTArr;

        // internal fields
        EVT_HANDLE  EvtHandleVal;
        LPCWSTR     XmlVal;
        LPCWSTR*    XmlValArr;
    };

    DWORD Count;   // number of elements (not length) in bytes.
    DWORD Type;

} EVT_VARIANT, *PEVT_VARIANT;

typedef enum _EVT_LOG_PROPERTY_ID
{
    EvtLogCreationTime = 0,             // EvtVarTypeFileTime
    EvtLogLastAccessTime,               // EvtVarTypeFileTime
    EvtLogLastWriteTime,                // EvtVarTypeFileTime
    EvtLogFileSize,                     // EvtVarTypeUInt64
    EvtLogAttributes,                   // EvtVarTypeUInt32
    EvtLogNumberOfLogRecords,           // EvtVarTypeUInt64
    EvtLogOldestRecordNumber,           // EvtVarTypeUInt64
    EvtLogFull,                         // EvtVarTypeBoolean

} EVT_LOG_PROPERTY_ID;

typedef enum _EVT_RENDER_CONTEXT_FLAGS
{
    EvtRenderContextValues = 0,         // Render specific properties
    EvtRenderContextSystem,             // Render all system properties (System)
    EvtRenderContextUser                // Render all user properties (User/EventData)

} EVT_RENDER_CONTEXT_FLAGS;

typedef enum _EVT_QUERY_FLAGS
{
    EvtQueryChannelPath                 = 0x1,
    EvtQueryFilePath                    = 0x2,

    EvtQueryForwardDirection            = 0x100,
    EvtQueryReverseDirection            = 0x200,

    EvtQueryTolerateQueryErrors         = 0x1000

} EVT_QUERY_FLAGS;

typedef enum _EVT_RENDER_FLAGS
{
    EvtRenderEventValues = 0,           // Variants
    EvtRenderEventXml,                  // XML
    EvtRenderBookmark                   // Bookmark

} EVT_RENDER_FLAGS;

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

} EVT_FORMAT_MESSAGE_FLAGS;

typedef enum _EVT_OPEN_LOG_FLAGS
{
    EvtOpenChannelPath          = 0x1,
    EvtOpenFilePath             = 0x2

} EVT_OPEN_LOG_FLAGS;

typedef enum _EVT_VARIANT_TYPE
{
    EvtVarTypeNull        = 0,
    EvtVarTypeString      = 1,
    EvtVarTypeAnsiString  = 2,
    EvtVarTypeSByte       = 3,
    EvtVarTypeByte        = 4,
    EvtVarTypeInt16       = 5,
    EvtVarTypeUInt16      = 6,
    EvtVarTypeInt32       = 7,
    EvtVarTypeUInt32      = 8,
    EvtVarTypeInt64       = 9,
    EvtVarTypeUInt64      = 10,
    EvtVarTypeSingle      = 11,
    EvtVarTypeDouble      = 12,
    EvtVarTypeBoolean     = 13,
    EvtVarTypeBinary      = 14,
    EvtVarTypeGuid        = 15,
    EvtVarTypeSizeT       = 16,
    EvtVarTypeFileTime    = 17,
    EvtVarTypeSysTime     = 18,
    EvtVarTypeSid         = 19,
    EvtVarTypeHexInt32    = 20,
    EvtVarTypeHexInt64    = 21,

    // these types used internally
    EvtVarTypeEvtHandle   = 32,
    EvtVarTypeEvtXml      = 35

} EVT_VARIANT_TYPE;

int	process_eventlog(const char *source, zbx_uint64_t *lastlogsize, unsigned long *out_timestamp,
		char **out_source, unsigned short *out_severity, char **out_message, unsigned long *out_eventid,
		unsigned char skip_old_data);

#endif /* ZABBIX_EVENTLOG_H */
