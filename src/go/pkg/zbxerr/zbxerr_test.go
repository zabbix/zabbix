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

package zbxerr

import (
	"errors"
	"reflect"
	"testing"
)

var (
	errorFooBar = errors.New("foo bar")
	errorFoo    = errors.New("foo")
	errorBar    = errors.New("bar")
)

func TestNew(t *testing.T) {
	type args struct {
		msg string
	}
	tests := []struct {
		name string
		args args
		want ZabbixError
	}{
		{
			"New must create a new ZabbixError with a corresponding message",
			args{"foo"},
			ZabbixError{errorFoo, nil},
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := New(tt.args.msg); !reflect.DeepEqual(got, tt.want) {
				t.Errorf("New() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestZabbixError_Cause(t *testing.T) {
	type fields struct {
		err   error
		cause error
	}
	tests := []struct {
		name   string
		fields fields
		want   error
	}{
		{
			"Cause must return a cause of original error",
			fields{errorFoo, errorBar},
			errorBar,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			e := ZabbixError{
				err:   tt.fields.err,
				cause: tt.fields.cause,
			}
			if got := e.Cause(); got != tt.want {
				t.Errorf("Cause() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestZabbixError_Error(t *testing.T) {
	tests := []struct {
		name string
		e    ZabbixError
		want string
	}{
		{
			"ZabbixError stringify",
			ZabbixError{errorFooBar, nil},
			"Foo bar.",
		},
		{
			"ZabbixError stringify with wrapping",
			ZabbixError{errorFoo, errorBar},
			"Foo: bar.",
		},
		{
			"ZabbixError stringify with wrapped ZabbixError",
			ZabbixError{ZabbixError{errorFoo, nil}, errorBar},
			"Foo: bar.",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := tt.e.Error(); got != tt.want {
				t.Errorf("Error() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestZabbixError_Raw(t *testing.T) {
	type fields struct {
		err   error
		cause error
	}
	tests := []struct {
		name   string
		fields fields
		want   string
	}{
		{
			"Raw must return a non-modified error message",
			fields{errorFoo, nil},
			"foo",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			e := ZabbixError{
				err:   tt.fields.err,
				cause: tt.fields.cause,
			}
			if got := e.Raw(); got != tt.want {
				t.Errorf("Raw() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestZabbixError_Unwrap(t *testing.T) {
	type fields struct {
		err   error
		cause error
	}
	tests := []struct {
		name   string
		fields fields
		want   error
	}{
		{
			"Unwrap must return an original underlying error",
			fields{errorFoo, nil},
			errorFoo,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			e := ZabbixError{
				err:   tt.fields.err,
				cause: tt.fields.cause,
			}
			if got := e.Unwrap(); got != tt.want {
				t.Errorf("Unwrap() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestZabbixError_Wrap(t *testing.T) {
	type fields struct {
		err   error
		cause error
	}
	type args struct {
		cause error
	}
	tests := []struct {
		name   string
		fields fields
		args   args
		want   error
	}{
		{
			"Wrap must return a new ZabbixError with wrapped cause",
			fields{errorFoo, nil},
			args{errorBar},
			ZabbixError{ZabbixError{errorFoo, nil}, errorBar},
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			e := ZabbixError{
				err:   tt.fields.err,
				cause: tt.fields.cause,
			}
			if got := e.Wrap(tt.args.cause); got != tt.want {
				t.Errorf("Wrap() = %v, want %v", got, tt.want)
			}
		})
	}
}
