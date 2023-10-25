/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

package mysql

import (
	"reflect"
	"testing"

	"git.zabbix.com/ap/plugin-support/metric"
	"git.zabbix.com/ap/plugin-support/tlsconfig"
)

func Test_getTLSDetails(t *testing.T) {
	type args struct {
		params map[string]string
	}
	tests := []struct {
		name    string
		args    args
		want    *tlsconfig.Details
		wantErr bool
	}{
		{
			"required",
			args{
				map[string]string{
					metric.SessionParam: "test",
					tlsConnectParam:     "required",
					uriParam:            "127.0.0.1",
				},
			},
			&tlsconfig.Details{
				SessionName:        "test",
				TlsConnect:         "required",
				RawUri:             "127.0.0.1",
				AllowedConnections: map[string]bool{disable: true, require: true, verifyCa: true, verifyFull: true},
			},
			false,
		},
		{
			"verify_ca",
			args{
				map[string]string{
					metric.SessionParam: "test",
					tlsConnectParam:     "verify_ca",
					tlsCAParam:          "path/to/ca",
					uriParam:            "127.0.0.1",
				},
			},
			&tlsconfig.Details{
				SessionName:        "test",
				TlsConnect:         "verify_ca",
				TlsCaFile:          "path/to/ca",
				RawUri:             "127.0.0.1",
				AllowedConnections: map[string]bool{disable: true, require: true, verifyCa: true, verifyFull: true},
			},
			false,
		},
		{
			"verify_full and check clients certs",
			args{
				map[string]string{
					metric.SessionParam: "test",
					tlsConnectParam:     "verify_ca",
					tlsCAParam:          "path/to/ca",
					tlsCertParam:        "path/to/cert",
					tlsKeyParam:         "path/to/key",
					uriParam:            "127.0.0.1",
				},
			},
			&tlsconfig.Details{
				SessionName:        "test",
				TlsConnect:         "verify_ca",
				TlsCaFile:          "path/to/ca",
				TlsCertFile:        "path/to/cert",
				TlsKeyFile:         "path/to/key",
				RawUri:             "127.0.0.1",
				AllowedConnections: map[string]bool{disable: true, require: true, verifyCa: true, verifyFull: true},
			},
			false,
		},
		{
			"missing ca file",
			args{
				map[string]string{
					metric.SessionParam: "test",
					tlsConnectParam:     "verify_ca",
					uriParam:            "127.0.0.1",
				},
			},
			nil,
			true,
		},
		{
			"missing client key file",
			args{
				map[string]string{
					metric.SessionParam: "test",
					tlsConnectParam:     "verify_ca",
					tlsCAParam:          "path/to/ca",
					tlsCertParam:        "path/to/cert",
					uriParam:            "127.0.0.1",
				},
			},
			nil,
			true,
		},
		{
			"missing client cert file",
			args{
				map[string]string{
					metric.SessionParam: "test",
					tlsConnectParam:     "verify_ca",
					tlsCAParam:          "path/to/ca",
					tlsKeyParam:         "path/to/key",
					uriParam:            "127.0.0.1",
				},
			},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := getTLSDetails(tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Errorf("getTLSDetails() error = %v, wantErr %v", err, tt.wantErr)

				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("getTLSDetails() = %v, want %v", got, tt.want)
			}
		})
	}
}
