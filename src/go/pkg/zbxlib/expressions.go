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

package zbxlib

/*
#cgo CFLAGS: -I${SRCDIR}/../../../../../include

#include "common.h"
#include "zbxalgo.h"
#include "zbxregexp.h"

typedef zbx_vector_ptr_t * zbx_vector_ptr_lp_t;

static void *new_global_regexp()
{
	zbx_vector_ptr_t *regexps;
	regexps = malloc(sizeof(zbx_vector_ptr_t));
	zbx_vector_ptr_create(regexps);
	return (void *)regexps;
}

static void	free_global_regexp(zbx_vector_ptr_t *regexps)
{
	zbx_regexp_clean_expressions(regexps);
	zbx_vector_ptr_destroy(regexps);
	free(regexps);
}

*/
import "C"
import (
	"errors"
	"unsafe"
)

func NewGlobalRegexp() (grxp unsafe.Pointer) {
	return unsafe.Pointer(C.new_global_regexp())
}

func DestroyGlobalRegexp(grxp unsafe.Pointer) {
	C.free_global_regexp(C.zbx_vector_ptr_lp_t(grxp))
}

func AddGlobalRegexp(grxp unsafe.Pointer, name, body string, expr_type int, delim byte, mode int) {
	cname := C.CString(name)
	cbody := C.CString(body)
	C.add_regexp_ex(C.zbx_vector_ptr_lp_t(grxp), cname, cbody, C.int(expr_type), C.char(delim), C.int(mode))
	C.free(unsafe.Pointer(cname))
	C.free(unsafe.Pointer(cbody))
}

func MatchGlobalRegexp(
	grxp unsafe.Pointer,
	value, pattern string,
	mode int,
	output_template *string) (match bool, output string, err error) {

	cvalue := C.CString(value)
	cpattern := C.CString(pattern)
	var ctemplate, coutput *C.char
	if output_template != nil {
		ctemplate = C.CString(*output_template)
		defer C.free(unsafe.Pointer(ctemplate))
	}

	ret := C.regexp_sub_ex(C.zbx_vector_ptr_lp_t(grxp), cvalue, cpattern, C.int(mode), ctemplate, &coutput)
	switch ret {
	case C.ZBX_REGEXP_MATCH:
		match = true
		if coutput != nil {
			output = C.GoString(coutput)
		}
	case C.ZBX_REGEXP_NO_MATCH:
		match = false
	default:
		err = errors.New("invalid global regular expression")
	}

	C.free(unsafe.Pointer(cvalue))
	C.free(unsafe.Pointer(cpattern))
	if coutput != nil {
		C.free(unsafe.Pointer(coutput))
	}
	return
}
