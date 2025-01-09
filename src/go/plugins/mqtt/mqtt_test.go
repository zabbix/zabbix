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

/*
** We use the library Eclipse Paho (eclipse/paho.mqtt.golang), which is
** distributed under the terms of the Eclipse Distribution License 1.0 (The 3-Clause BSD License)
** available at https://www.eclipse.org/org/documents/edl-v10.php
**/

package mqtt

import (
	"math/rand"
	"testing"

	"github.com/google/go-cmp/cmp"
)

func Test_getClientID(t *testing.T) {
	t.Parallel()

	type args struct {
		src rand.Source
	}

	tests := []struct {
		name string
		args args
		want string
	}{
		{
			"+valid",
			args{rand.NewSource(10)},
			"ZabbixAgent2wSv9wq3T",
		},
	}
	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got := getClientID(tt.args.src)
			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Fatalf("getClientID() = %s", diff)
			}
		})
	}
}
