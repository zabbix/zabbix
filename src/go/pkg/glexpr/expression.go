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

package glexpr

import (
	"runtime"
	"sort"
	"unsafe"

	"zabbix.com/pkg/zbxlib"
)

const (
	CaseInsensitive = iota
	CaseSensitive
)

type Expression struct {
	Name      string  `json:"name"`
	Body      string  `json:"expression"`
	Type      *int    `json:"expression_type"`
	Delimiter *string `json:"exp_delimiter"`
	Mode      *int    `json:"case_sensitive"`
}

type Bundle struct {
	expressions []*Expression
	Cblob       unsafe.Pointer
}

func SortExpressions(expressions []*Expression) {
	sort.Slice(expressions, func(i, j int) bool {
		if expressions[i].Name != expressions[j].Name {
			return expressions[i].Name < expressions[j].Name
		}
		if expressions[i].Body != expressions[j].Body {
			return expressions[i].Body < expressions[j].Body
		}
		if *expressions[i].Type != *expressions[j].Type {
			return *expressions[i].Type < *expressions[j].Type
		}
		if *expressions[i].Mode != *expressions[j].Mode {
			return *expressions[i].Mode < *expressions[j].Mode
		}
		return *expressions[i].Delimiter < *expressions[j].Delimiter
	})
}

func (b *Bundle) CompareExpressions(expressions []*Expression) bool {
	if len(expressions) != len(b.expressions) {
		return false
	}
	for i := range expressions {
		l := b.expressions[i]
		r := expressions[i]
		if l.Name != r.Name || l.Body != r.Body || *l.Type != *r.Type || *l.Mode != *r.Mode || *l.Delimiter != *r.Delimiter {
			return false
		}
	}
	return true
}

func (b *Bundle) Match(value string, pattern string, mode int, output_template *string) (match bool, output string) {
	match, output, _ = zbxlib.MatchGlobalRegexp(b.Cblob, value, pattern, mode, output_template)
	return
}

func NewBundle(expressions []*Expression) (bundle *Bundle) {
	bundle = &Bundle{expressions: expressions}
	bundle.Cblob = zbxlib.NewGlobalRegexp()
	for _, e := range expressions {
		var delimeter byte = ','

		if 0 != len(*e.Delimiter) {
			delimeter = (*e.Delimiter)[0]
		}

		zbxlib.AddGlobalRegexp(bundle.Cblob, e.Name, e.Body, *e.Type, delimeter, *e.Mode)
	}
	runtime.SetFinalizer(bundle, func(b *Bundle) { zbxlib.DestroyGlobalRegexp(b.Cblob) })
	return
}
