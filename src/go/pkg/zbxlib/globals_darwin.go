/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package zbxlib

/*
#cgo LDFLAGS: ${SRCDIR}/../../../zabbix_agent/logfiles/libzbxlogfiles.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxcomms/libzbxcomms.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxcommon/libzbxcommon.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxcrypto/libzbxcrypto.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxsys/libzbxsys.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxnix/libzbxnix.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxconf/libzbxconf.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxhttp/libzbxhttp.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxcompress/libzbxcompress.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxregexp/libzbxregexp.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxsysinfo/libzbxagentsysinfo.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxsysinfo/common/libcommonsysinfo.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxsysinfo/simple/libsimplesysinfo.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxexec/libzbxexec.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxalgo/libzbxalgo.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxjson/libzbxjson.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxsysinfo/osx/libspechostnamesysinfo.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxsysinfo/osx/libspecsysinfo.a
#cgo LDFLAGS: -lz -lpcre -lresolv
*/
import "C"
