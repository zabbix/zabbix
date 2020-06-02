// +build postgres_tests

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

package postgres

/*
func Test_newPluginConfig(t *testing.T) {
	const globalTimeout, MinTimeout, KeepAlive = 30, 1, 300

	type args struct {
		optionKeepAlive time.Duration
		optionsTimeout time.Duration
	}
	tests := []struct {
		name    string
		args    args
		want    *PluginOptions
		wantErr bool
	}{
		{
			"Should return config with default values",
			args{map[string]string{}},
			&PluginOptions{
				Host:      "localhost",
				Port:      DefaultPort,
				Database:  "postgres",
				User:      "postgres",
				Password:  "postgres",
				Timeout:   10,
				KeepAlive: 300,
			},
			false,
		},
		{
			"Overwrite the defaults",
			args{map[string]string{

				"Host":      "localhost",
				"Port":      "5433",
				"Database":  "zabbix",
				"User":      "zabbix",
				"Password":  "zabbix",
				"Timeout":   "5",
				"KeepAlive": "300",
			}},
			&PluginOptions{
				Host:      "localhost",
				Port:      5433,
				Database:  "zabbix",
				User:      "zabbix",
				Password:  "zabbix",
				Timeout:   5,
				KeepAlive: 300,
			},
			false,
		},
		{
			"Should fail on malformed PGHost parameter",
			args{map[string]string{"PGHost": ""}},
			nil,
			true,
		},
		{
			"Should fail if Timeout is not integer",
			args{map[string]string{"Timeout": "foo"}},
			nil,
			true,
		},
		{
			"Should fail if Timeout is less than " + strconv.Itoa(MinTimeout),
			args{map[string]string{"Timeout": strconv.Itoa(MinTimeout - 1)}},
			nil,
			true,
		},
		{
			"Should fail if Timeout is greater than global agent timeout",
			args{map[string]string{"Timeout": strconv.Itoa(globalTimeout + 1)}},
			nil,
			true,
		},
		{
			"Should fail if KeepAlive is not integer",
			args{map[string]string{"Keepalive": "foo"}},
			nil,
			true,
		},
		{
			"Should fail if KeepAlive is less than " + strconv.Itoa(KeepAlive),
			args{map[string]string{"KeepAlive": strconv.Itoa(KeepAlive - 1)}},
			nil,
			true,
		},
		{
			"Should fail if KeepAlive is greater than " + strconv.Itoa(KeepAlive),
			args{map[string]string{"KeepAlive": strconv.Itoa(KeepAlive + 1)}},
			nil,
			true,
		},
		{
			"Should fail if unknown parameter is passed",
			args{map[string]string{"foo": "bar"}},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := NewConnManager(tt.args.options)
			if (err != nil) != tt.wantErr {
				t.Errorf("newPluginConfig() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("newPluginConfig() = %v, want %v", got, tt.want)
			}
		})
	}
}
*/
