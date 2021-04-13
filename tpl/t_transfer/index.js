app.controller('transferCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/t_transfer";
    var master = 'Transaksi Transfer Kas';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.is_setting_field = false;
    $scope.form = {};
    $scope.is_group = false;
    /*
     * SETTING FIELD
     */
    $scope.checklist = false;
    $scope.field = [];
    $scope.startFrom = [];
    $scope.limit = 0;
    $scope.row = 4;
    $scope.classrow = 12 / $scope.row;
    $scope.setPosition = function ($event, key, vals) {
        $event.preventDefault();
        $event.stopPropagation();
        var ps = $scope.limit;
        if ($event.keyCode == 37) {
            ps = -($scope.limit);
        } else if ($event.keyCode == 38) {
            ps = -1;
        } else if ($event.keyCode == 40) {
            ps = 1;
        }
        if ($event.keyCode == 37 || $event.keyCode == 39 || $event.keyCode == 38 || $event.keyCode == 40) {
            $event.preventDefault();
            var sw = $scope.field[key + ps].value;
            var chk = $scope.field[key + ps].checkbox;
            var al = $scope.field[key + ps].alias;
            $scope.field[key + ps].value = vals.value;
            $scope.field[key + ps].checkbox = vals.checkbox;
            $scope.field[key + ps].alias = vals.alias;
            $scope.field[key].value = sw;
            $scope.field[key].checkbox = chk;
            $scope.field[key].alias = al;
            var f = key + ps;
            setTimeout(function () {
                $('.input-' + f).focus()
            }, 1)
        } else {
            $scope.field[key].alias = vals.alias;
        }
    }
    $scope.fillCheckBox = function (a) {
        angular.forEach($scope.field, function (val, key) {
            val.checkbox = a;
        })
    }
    $scope.savePosition = function () {
        Data.post(control_link + '/savePosition', $scope.field).then(function (result) {
            if (result.status_code == 200) {
                //                $scope.is_setting = false;
                $scope.callServer(tableStateRef)
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
            }
        });
    }
    /*
     * END SETTING FIELD
     */
    Data.get('acc/m_akun/getAkunGroup').then(function (data) {
        $scope.is_group = data.data.is_group;
        if ($scope.is_group == true) {
            $scope.listAkunGroup = data.data.list;
        }

    })

    $scope.getAkunKasAsal = function (array) {
        var params = {};
        params.m_akun_group_id = $scope.form.m_akun_group_asal_id != undefined ? $scope.form.m_akun_group_asal_id.id : null;

        Data.get('acc/m_akun/akunKas', params).then(function (data) {
            $scope.akunAsal = data.data.list;
        });
    }
    $scope.getAkunKasTujuan = function (array) {
        var params = {};
        params.m_akun_group_id = $scope.form.m_akun_group_tujuan_id != undefined ? $scope.form.m_akun_group_tujuan_id.id : null;

        Data.get('acc/m_akun/akunKas', params).then(function (data) {
            $scope.akunTujuan = data.data.list;
        });
    }

    Data.get('acc/m_akun/getTanggalSetting').then(function (response) {
        $scope.tanggal_setting = response.data.tanggal;
        $scope.options = {
            minDate: new Date(response.data.tanggal),
        };
    });
    $scope.master = master;
    $scope.callServer = function callServer(tableState) {
        tableStateRef = tableState;
        $scope.isLoading = true;
        var offset = tableState.pagination.start || 0;
        var limit = tableState.pagination.number || 1000;
        /** set offset and limit */
        var param = {
            offset: offset,
            limit: limit
        };
        /** set sort and order */
        if (tableState.sort.predicate) {
            param['sort'] = tableState.sort.predicate;
            param['order'] = tableState.sort.reverse;
        }
        /** set filter */
        if (tableState.search.predicateObject) {
            param['filter'] = tableState.search.predicateObject;
        }
        Data.get('acc/m_lokasi/getLokasi', param).then(function (response) {
            $scope.listLokasi = response.data.list;
        });
        Data.get(control_link + '/index', param).then(function (response) {
            $scope.displayed = response.data.list;
            if (response.data.field != undefined && response.data.field.length > 0) {
                $scope.field = response.data.field;
            } else {
                var index = 0;
                angular.forEach(response.data.list[0], function (val, key) {
                    $scope.field.push({
                        checkbox: true,
                        value: key,
                        alias: key,
                        no: index
                    });
                    index += 1;
                });
            }
            $scope.limit = Math.ceil($scope.field.length / $scope.row);
            $scope.startFrom = [];
            angular.forEach($scope.field, function (val, key) {
                if (val.no % $scope.limit == 0) {
                    $scope.startFrom.push({
                        start: val.no,
                        limit: $scope.limit
                    })
                }
            })
            $scope.base_url = response.data.base_url;
            tableState.pagination.numberOfPages = Math.ceil(response.data.totalItems / limit);
        });
        $scope.isLoading = false;
    };
    $scope.kode = function (lokasi) {
        Data.get(control_link + '/kode/' + lokasi.kode).then(function (response) {
            $scope.form.no_transaksi = response.data.kode;
            $scope.form.no_urut = response.data.urutan;
        });
    };
    /** create */
    $scope.create = function () {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.is_disable = false;
        $scope.formtitle = master + " | Form Tambah Data";
        $scope.form = {};
        if ($scope.listLokasi.length > 0) {
            $scope.form.m_lokasi_asal_id = $scope.listLokasi[0];
            $scope.form.m_lokasi_tujuan_id = $scope.listLokasi[0];
        }
        if ($scope.listAkunGroup.length > 0) {
            $scope.form.m_akun_group_asal_id = $scope.listAkunGroup[0];
            $scope.form.m_akun_group_tujuan_id = $scope.listAkunGroup[0];
        }
        $scope.form.tanggal = new Date($scope.tanggal_setting);
        if (new Date() >= new Date($scope.tanggal_setting)) {
            $scope.form.tanggal = new Date();
        }
        $scope.getAkunKasAsal()
        $scope.getAkunKasTujuan()
        $scope.listDetail = [{}];
    };
    /** update */
    $scope.update = function (form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_update = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Edit Data : " + form.no_transaksi;
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal);
        $scope.getAkunKasAsal()
        $scope.getAkunKasTujuan()
    };
    /** view */
    $scope.view = function (form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Lihat Data : " + form.no_transaksi;
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal);
    };
    /** save action */
    $scope.save = function (form, type_save) {
        form["status"] = type_save;
        var data = {
            form: form,
        }
        Data.post(control_link + '/save', data).then(function (result) {
            if (result.status_code == 200) {
                $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                $scope.cancel();
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
            }
        });
    };
    /** cancel action */
    $scope.cancel = function () {
        if (!$scope.is_view) {
            $scope.callServer(tableStateRef);
        }
        $scope.is_edit = false;
        $scope.is_view = false;
    };
    $scope.trash = function (row) {
        var data = angular.copy(row);
        Swal.fire({
            title: "Peringatan ! ",
            text: "Apakah Anda Yakin Ingin Menghapus Data Ini",
            type: "warning",
            showCancelButton: true,
            confirmButtonColor: "#DD6B55",
            confirmButtonText: "Iya, di Hapus",
            cancelButtonText: "Tidak",
        }).then((result) => {
            if (result.value) {
                row.is_deleted = 1;
                Data.post(control_link + '/trash', row).then(function (result) {
                    $rootScope.alert("Berhasil", "Data berhasil dihapus", "success");
                    $scope.cancel();
                });
            }
        });
    };
    $scope.restore = function (row) {
        var data = angular.copy(row);
        Swal.fire({
            title: "Peringatan ! ",
            text: "Apakah Anda Yakin Ingin Merestore Data Ini",
            type: "warning",
            showCancelButton: true,
            confirmButtonColor: "#DD6B55",
            confirmButtonText: "Iya, di Restore",
            cancelButtonText: "Tidak",
        }).then((result) => {
            if (result.value) {
                row.is_deleted = 0;
                Data.post(control_link + '/trash', row).then(function (result) {
                    $rootScope.alert("Berhasil", "Data berhasil direstore", "success");
                    $scope.cancel();
                });
            }
        });
    };
    $scope.delete = function (row) {
        var data = angular.copy(row);
        Swal.fire({
            title: "Peringatan ! ",
            text: "Apakah Anda Yakin Ingin Menghapus Permanen Data Ini",
            type: "warning",
            showCancelButton: true,
            confirmButtonColor: "#DD6B55",
            confirmButtonText: "Iya, di Hapus",
            cancelButtonText: "Tidak",
        }).then((result) => {
            if (result.value) {
                row.is_deleted = 1;
                Data.post(control_link + '/delete', row).then(function (result) {
                    $rootScope.alert("Berhasil", "Data berhasil dihapus permanen", "success");
                    $scope.cancel();
                });
            }
        });
    };
});