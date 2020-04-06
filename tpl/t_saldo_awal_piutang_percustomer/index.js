app.controller('piutangpercustomerCtrl', function($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/t_saldo_awal_piutang_percustomer";
    var master = 'Transaksi Saldo Awal Piutang Per Customer';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.form = {};
    $scope.unitKeuangan = [];
    Data.get("acc/m_lokasi/getLokasi").then(function(result) {
        $scope.listLokasi = result.data;
    });
    Data.get('acc/m_akun/akunDetail').then(function(data) {
        $scope.listAkun = data.data.list;
    });
    Data.get('acc/m_akun/akunPiutang').then(function(data) {
        $scope.listAkunPiutang = data.data.list;
    });
    Data.get('acc/m_akun/getTanggalSetting').then(function(response) {
        $scope.tanggal_setting = response.data.tanggal;
        $scope.options = {
        };
    });
    $scope.resetFilter = function(filter) {
        $scope.form[filter] = undefined;
        $scope.filterIndex();
    }
    $scope.getCustomer = function(val) {
        var param = {
            val: val
        };
        Data.get("acc/m_customer/getCustomer", param).then(function(response) {
            $scope.listCustomer = response.data.list;
        });
    };
    $scope.setDataCustomer = function(data) {
        $scope.form.tlp = data.tlp;
        $scope.form.email = data.email;
        $scope.form.alamat = data.alamat;
    };
    $scope.filterIndex = function() {
        $scope.callServer(tableStateRef);
    }
    $scope.master = master;
    $scope.callServer = function callServer(tableState) {
        tableStateRef = tableState;
        $scope.isLoading = true;
        var offset = tableState.pagination.start || 0;
        var limit = tableState.pagination.number || 1000;
        /** set offset and limit */
        var param = {
            limit: limit,
            offset: offset,
            m_customer_id: $scope.form.customer != undefined ? $scope.form.customer.id : undefined,
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
        Data.get('acc/m_lokasi/getLokasi', param).then(function(response) {
            $scope.listLokasi = response.data.list;
        });
        Data.get(control_link + '/index', param).then(function(response) {
            $scope.displayed = response.data.list;
            $scope.base_url = response.data.base_url;
            tableState.pagination.numberOfPages = Math.ceil(response.data.totalItems / limit);
        });
        $scope.isLoading = false;
    };
    $scope.kode = function(lokasi) {
        Data.get(control_link + '/kode/' + lokasi.kode).then(function(response) {
            $scope.form.no_transaksi = response.data.kode;
            $scope.form.no_urut = response.data.urutan;
        });
    };
    /** create */
    $scope.create = function() {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.is_disable = false;
        $scope.formtitle = master + " | Form Tambah Data";
        $scope.form = {};
        if ($scope.listAkun.length > 0) {
            $scope.form.akun = $scope.listAkun[0];
        }
        $scope.form.tanggal = new Date($scope.tanggal_setting);
        $scope.form.jatuh_tempo = new Date($scope.tanggal_setting);
        if (new Date() >= new Date($scope.tanggal_setting)) {
            $scope.form.tanggal = new Date();
            $scope.form.jatuh_tempo = new Date();
        }
        $scope.listDetail = [{}];
    };
    /** update */
    $scope.update = function(form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_update = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Edit Data : " + form.kode;
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal);
        $scope.form.jatuh_tempo = new Date(form.jatuh_tempo);
        $scope.setDataCustomer(form.customer);
    };
    /** view */
    $scope.view = function(form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Lihat Data : " + form.kode;
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal);
        $scope.form.jatuh_tempo = new Date(form.jatuh_tempo);
        $scope.setDataCustomer(form.customer);
    };
    /** save action */
    $scope.save = function(form, type_save) {
        form["status"] = type_save;
        Data.post(control_link + '/save', form).then(function(result) {
            if (result.status_code == 200) {
                $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                $scope.cancel();
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
            }
        });
    };
    /** cancel action */
    $scope.cancel = function() {
        if (!$scope.is_view) {
            $scope.callServer(tableStateRef);
        }
        $scope.is_edit = false;
        $scope.is_view = false;
    };
    $scope.trash = function(row) {
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
                Data.post(control_link + '/trash', row).then(function(result) {
                    $rootScope.alert("Berhasil", "Data berhasil dihapus", "success");
                    $scope.cancel();
                });
            }
        });
    };
    $scope.restore = function(row) {
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
                Data.post(control_link + '/trash', row).then(function(result) {
                    $rootScope.alert("Berhasil", "Data berhasil direstore", "success");
                    $scope.cancel();
                });
            }
        });
    };
    $scope.delete = function(row) {
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
                Data.post(control_link + '/delete', row).then(function(result) {
                    $rootScope.alert("Berhasil", "Data berhasil dihapus permanen", "success");
                    $scope.cancel();
                });
            }
        });
    };
});