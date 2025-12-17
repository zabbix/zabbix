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

package redis

import (
	"testing"

	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/sdk/plugin/comms"
	"golang.zabbix.com/sdk/tlsconfig"
)

func Test_session_getFieldValues(t *testing.T) {
	t.Parallel()

	type fields struct {
		URI         string
		Password    string
		User        string
		TLSConnect  string
		TLSCAFile   string
		TLSCertFile string
		TLSKeyFile  string
	}

	tests := []struct {
		name   string
		fields fields
		want   map[comms.ConfigSetting]string
	}{
		{
			name: "+allFields",
			fields: fields{
				URI:         "redis://localhost:6379",
				Password:    "secret",
				User:        "testuser",
				TLSConnect:  string(tlsconfig.Disabled),
				TLSCAFile:   "/path/to/ca.pem",
				TLSCertFile: "/path/to/cert.pem",
				TLSKeyFile:  "/path/to/key.pem",
			},
			want: map[comms.ConfigSetting]string{
				comms.URI:         "redis://localhost:6379",
				comms.Password:    "secret",
				comms.User:        "testuser",
				comms.TLSConnect:  string(tlsconfig.Disabled),
				comms.TLSCAFile:   "/path/to/ca.pem",
				comms.TLSCertFile: "/path/to/cert.pem",
				comms.TLSKeyFile:  "/path/to/key.pem",
			},
		},
		{
			name:   "+emptySession",
			fields: fields{},
			want: map[comms.ConfigSetting]string{
				comms.URI:         "",
				comms.Password:    "",
				comms.User:        "",
				comms.TLSConnect:  "",
				comms.TLSCAFile:   "",
				comms.TLSCertFile: "",
				comms.TLSKeyFile:  "",
			},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			s := &session{
				URI:         tt.fields.URI,
				Password:    tt.fields.Password,
				User:        tt.fields.User,
				TLSConnect:  tt.fields.TLSConnect,
				TLSCAFile:   tt.fields.TLSCAFile,
				TLSCertFile: tt.fields.TLSCertFile,
				TLSKeyFile:  tt.fields.TLSKeyFile,
			}

			got := s.getFieldValues()

			diff := cmp.Diff(tt.want, got)
			if diff != "" {
				t.Fatalf("session.getFieldValues() = %s", diff)
			}
		})
	}
}

func Test_session_validateSession(t *testing.T) {
	t.Parallel()

	type fields struct {
		URI         string
		Password    string
		User        string
		TLSConnect  string
		TLSCAFile   string
		TLSCertFile string
		TLSKeyFile  string
	}

	type args struct {
		defaults *session
	}

	tests := []struct {
		name    string
		fields  fields
		args    args
		wantErr bool
	}{
		{
			name: "+validSessionWithTLSDisabled",
			fields: fields{
				URI:        "redis://localhost:6379",
				TLSConnect: string(tlsconfig.Disabled),
				Password:   "abc",
			},
			args: args{
				defaults: &session{},
			},
			wantErr: false,
		},
		{
			name: "+validSessionWithTLSFull",
			fields: fields{
				URI:         "redis://localhost:6379",
				Password:    "abc",
				TLSConnect:  string(tlsconfig.VerifyFull),
				TLSCAFile:   "/path/to/ca.pem",
				TLSCertFile: "/path/to/cert.pem",
				TLSKeyFile:  "/path/to/key.pem",
			},
			args: args{
				defaults: &session{},
			},
			wantErr: false,
		},
		{
			name: "-invalidConnectType",
			fields: fields{
				URI:        "redis://localhost:6379",
				Password:   "abc",
				TLSConnect: "invalid",
			},
			args: args{
				defaults: &session{},
			},
			wantErr: true,
		},
		{
			name: "-inconsistentTLSFields",
			fields: fields{
				URI:        "redis://localhost:6379",
				Password:   "abc",
				TLSConnect: string(tlsconfig.VerifyFull),
				TLSCAFile:  "/path/to/ca.pem",
				TLSKeyFile: "/path/to/key.pem",
			},
			args: args{
				defaults: &session{
					TLSCertFile: "/path/to/cert.pem",
				},
			},
			wantErr: true,
		},
		{
			name: "-missingCACertificate",
			fields: fields{
				URI:        "redis://localhost:6379",
				Password:   "abc",
				TLSConnect: string(tlsconfig.VerifyCA),
			},
			args: args{
				defaults: &session{},
			},
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			s := &session{
				URI:         tt.fields.URI,
				Password:    tt.fields.Password,
				User:        tt.fields.User,
				TLSConnect:  tt.fields.TLSConnect,
				TLSCAFile:   tt.fields.TLSCAFile,
				TLSCertFile: tt.fields.TLSCertFile,
				TLSKeyFile:  tt.fields.TLSKeyFile,
			}

			err := s.validateSession(tt.args.defaults)
			if (err != nil) != tt.wantErr {
				t.Fatalf("session.validateSession() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}

func Test_session_resolveTLSConnect(t *testing.T) {
	t.Parallel()

	type fields struct {
		URI         string
		Password    string
		User        string
		TLSConnect  string
		TLSCAFile   string
		TLSCertFile string
		TLSKeyFile  string
	}

	type args struct {
		defaults *session
	}

	tests := []struct {
		name    string
		fields  fields
		args    args
		want    tlsconfig.TLSConnectionType
		wantErr bool
	}{
		{
			name: "+sessionTLSDisabled",
			fields: fields{
				TLSConnect: string(tlsconfig.Disabled),
			},
			args: args{
				defaults: &session{},
			},
			want:    tlsconfig.Disabled,
			wantErr: false,
		},
		{
			name: "+sessionTLSRequired",
			fields: fields{
				TLSConnect: string(tlsconfig.Required),
			},
			args: args{
				defaults: &session{},
			},
			want:    tlsconfig.Required,
			wantErr: false,
		},
		{
			name:   "+defaultsTLSFallback",
			fields: fields{},
			args: args{
				defaults: &session{
					TLSConnect: string(tlsconfig.Required),
				},
			},
			want:    tlsconfig.Required,
			wantErr: false,
		},
		{
			name:   "+defaultDisabled",
			fields: fields{},
			args: args{
				defaults: &session{},
			},
			want:    tlsconfig.Disabled,
			wantErr: false,
		},
		{
			name: "-invalidSessionTLSConnect",
			fields: fields{
				TLSConnect: "invalid",
			},
			args: args{
				defaults: &session{},
			},
			want:    "",
			wantErr: true,
		},
		{
			name:   "-invalidTLSConnect",
			fields: fields{},
			args: args{
				defaults: &session{
					TLSConnect: "invalid",
				},
			},
			want:    "",
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			s := &session{
				URI:         tt.fields.URI,
				Password:    tt.fields.Password,
				User:        tt.fields.User,
				TLSConnect:  tt.fields.TLSConnect,
				TLSCAFile:   tt.fields.TLSCAFile,
				TLSCertFile: tt.fields.TLSCertFile,
				TLSKeyFile:  tt.fields.TLSKeyFile,
			}

			got, err := s.resolveTLSConnect(tt.args.defaults)
			if (err != nil) != tt.wantErr {
				t.Fatalf("session.resolveTLSConnect() error = %v, wantErr %v", err, tt.wantErr)
			}

			diff := cmp.Diff(tt.want, got)
			if diff != "" {
				t.Fatalf("session.resolveTLSConnect() = %s", diff)
			}
		})
	}
}

func Test_session_runSourceConsistencyValidation(t *testing.T) {
	t.Parallel()

	type fields struct {
		URI         string
		Password    string
		User        string
		TLSConnect  string
		TLSCAFile   string
		TLSCertFile string
		TLSKeyFile  string
	}

	tests := []struct {
		name    string
		fields  fields
		wantErr bool
	}{
		{
			name: "+allTLSFields",
			fields: fields{
				TLSCAFile:   "/path/to/ca.pem",
				TLSCertFile: "/path/to/cert.pem",
				TLSKeyFile:  "/path/to/key.pem",
			},
			wantErr: false,
		},
		{
			name:    "+allTLSFieldsEmpty",
			fields:  fields{},
			wantErr: false,
		},
		{
			name: "-mixedTLSFields",
			fields: fields{
				TLSCAFile:   "/path/to/ca.pem",
				TLSCertFile: "",
				TLSKeyFile:  "/path/to/key.pem",
			},
			wantErr: true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			s := &session{
				URI:         tt.fields.URI,
				Password:    tt.fields.Password,
				User:        tt.fields.User,
				TLSConnect:  tt.fields.TLSConnect,
				TLSCAFile:   tt.fields.TLSCAFile,
				TLSCertFile: tt.fields.TLSCertFile,
				TLSKeyFile:  tt.fields.TLSKeyFile,
			}

			err := s.runSourceConsistencyValidation()
			if (err != nil) != tt.wantErr {
				t.Fatalf("session.runSourceConsistencyValidation() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}

func Test_validateRequiredField(t *testing.T) {
	t.Parallel()

	type args struct {
		name         comms.ConfigSetting
		value        string
		defaultValue string
	}

	tests := []struct {
		name    string
		args    args
		wantErr bool
	}{
		{
			name: "+valueProvided",
			args: args{
				name:         comms.URI,
				value:        "redis://localhost:6379",
				defaultValue: "",
			},
			wantErr: false,
		},
		{
			name: "+defaultValueProvided",
			args: args{
				name:         comms.URI,
				value:        "",
				defaultValue: "redis://localhost:6379",
			},
			wantErr: false,
		},
		{

			name: "+bothValuesProvided",
			args: args{
				name:         comms.URI,
				value:        "redis://localhost:6379",
				defaultValue: "redis://default:6379",
			},
			wantErr: false,
		},
		{
			name: "-bothValuesEmpty",
			args: args{
				name:         comms.URI,
				value:        "",
				defaultValue: "",
			},
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			err := validateRequiredField(tt.args.name, tt.args.value, tt.args.defaultValue)
			if (err != nil) != tt.wantErr {
				t.Fatalf("validateRequiredField() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}

func Test_validateForbiddenField(t *testing.T) {
	t.Parallel()

	type args struct {
		name  comms.ConfigSetting
		value string
	}

	tests := []struct {
		name    string
		args    args
		wantErr bool
	}{

		{
			name: "+emptyValueAllowed",
			args: args{
				name:  comms.TLSCAFile,
				value: "",
			},
			wantErr: false,
		},
		{
			name: "-nonEmptyValueForbidden",
			args: args{
				name:  comms.TLSCAFile,
				value: "/path/to/ca.pem",
			},
			wantErr: true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			err := validateForbiddenField(tt.args.name, tt.args.value)
			if (err != nil) != tt.wantErr {
				t.Fatalf("validateForbiddenField() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}
