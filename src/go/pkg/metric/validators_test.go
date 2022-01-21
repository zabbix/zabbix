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

package metric

import "testing"

var (
	number    = "42"
	notNumber = "foo"
)

func TestNumberValidator_Validate(t *testing.T) {
	type args struct {
		value *string
	}
	tests := []struct {
		name    string
		args    args
		wantErr bool
	}{
		{
			name:    "Must successfully validate a number",
			args:    args{&number},
			wantErr: false,
		},
		{
			name:    "Must successfully validate nil",
			args:    args{nil},
			wantErr: false,
		},
		{
			name:    "Must fail if a given value is not a number",
			args:    args{&notNumber},
			wantErr: true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			v := NumberValidator{}
			if err := v.Validate(tt.args.value); (err != nil) != tt.wantErr {
				t.Errorf("Validate() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}

var (
	string1 = "hello123"
	string2 = "hello world"
)

func TestPatternValidator_Validate(t *testing.T) {
	type fields struct {
		Pattern string
	}
	type args struct {
		value *string
	}
	tests := []struct {
		name    string
		fields  fields
		args    args
		wantErr bool
	}{
		{
			name:    "Must successfully validate a string value",
			fields:  fields{"^hello[0-9]+$"},
			args:    args{&string1},
			wantErr: false,
		},
		{
			name:    "Must successfully validate nil",
			args:    args{nil},
			wantErr: false,
		},
		{
			name:    "Must fail if a given value does not match a given pattern",
			fields:  fields{"^hello[0-9]+$"},
			args:    args{&string2},
			wantErr: true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			v := PatternValidator{
				Pattern: tt.fields.Pattern,
			}
			if err := v.Validate(tt.args.value); (err != nil) != tt.wantErr {
				t.Errorf("Validate() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}

var (
	valInRange    = "50"
	valNotInRange = "1000"
)

func TestRangeValidator_Validate(t *testing.T) {
	type fields struct {
		Min int
		Max int
	}
	type args struct {
		value *string
	}
	tests := []struct {
		name    string
		fields  fields
		args    args
		wantErr bool
	}{
		{
			name:    "Must successfully validate a value in a range",
			fields:  fields{0, 100},
			args:    args{&valInRange},
			wantErr: false,
		},
		{
			name:    "Must successfully validate nil",
			args:    args{nil},
			wantErr: false,
		},
		{
			name:    "Must fail if a given value is out of a range",
			fields:  fields{0, 100},
			args:    args{&valNotInRange},
			wantErr: true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			v := RangeValidator{
				Min: tt.fields.Min,
				Max: tt.fields.Max,
			}
			if err := v.Validate(tt.args.value); (err != nil) != tt.wantErr {
				t.Errorf("Validate() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}

func TestSetValidator_Validate(t *testing.T) {
	type fields struct {
		Set []string
	}
	type args struct {
		value *string
	}
	tests := []struct {
		name    string
		fields  fields
		args    args
		wantErr bool
	}{
		{
			name:    "Must successfully validate a value in a set",
			fields:  fields{[]string{"foo", "42", "100500"}},
			args:    args{&number},
			wantErr: false,
		},
		{
			name:    "Must successfully validate nil",
			args:    args{nil},
			wantErr: false,
		},
		{
			name:    "Must fail if a given value is out of a set",
			fields:  fields{[]string{"foo", "42", "100500"}},
			args:    args{&string1},
			wantErr: true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			v := SetValidator{
				Set: tt.fields.Set,
			}
			if err := v.Validate(tt.args.value); (err != nil) != tt.wantErr {
				t.Errorf("Validate() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}
