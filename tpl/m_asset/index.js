app.controller('AssetCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/m_asset";
    var master = 'Master Asset Tetap';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;

    Data.get(control_link + '/getLokasi').then(function(data) {
        $scope.listLokasi = data.data;
    });

    $scope.master = master;
    $scope.callServer = function callServer(tableState) {
        tableStateRef = tableState;
        $scope.isLoading = true;
        var offset = tableState.pagination.start || 0;
        var limit = tableState.pagination.number || 20;
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
        Data.get(control_link + '/index', param).then(function (response) {
            $scope.displayed = response.data.list;
            tableState.pagination.numberOfPages = Math.ceil(response.data.totalItems / limit);
            $scope.base_url = response.data.base_url;
        });
        $scope.isLoading = false;
    };

    Data.get(control_link + '/getAkun').then(function (response) {
        $scope.listakun = response.data.list;
    });

//    Data.get('acc/m_lokasi/index', {filter: {is_deleted: 0}}).then(function (response) {
//        $scope.listLokasi = response.data.list;
//        // $scope.listLokasi.push({"id":-1,"nama":"Lainya" });
//
//    });

    Data.get('acc/m_umur_ekonomis/index', {filter: {is_deleted: 0}}).then(function (response) {
        $scope.listUmur = response.data.list;
    });

    $scope.setTahun = function (persentase) {
        $scope.form.persentase = persentase;
    }

    /** create */
    $scope.create = function () {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.is_disable = false;
        $scope.formtitle = master + " | Form Tambah Data";
        $scope.form = {};
        $scope.form.tanggal = new Date();
        $scope.form.tgl_mulai_penyusutan = new Date();
        $scope.form.is_penyusutan = 0;
        Data.get('acc/m_asset/generateKode').then(function (response) {
            $scope.form.kode = response.data;
        });
    };
    // $scope.create();
    /** update */
    $scope.update = function (form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_update = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Edit Data : " + form.nama;
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal_beli);
        if (form.tgl_mulai_penyusutan != null) {
            $scope.form.tgl_mulai_penyusutan = new Date(form.tgl_mulai_penyusutan);
        }
        $scope.form.harga = form.harga_beli;
    };
    /** view */
    $scope.view = function (form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Lihat Data : " + form.nama;
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal_beli);
        if (form.tgl_mulai_penyusutan != null) {
            $scope.form.tgl_mulai_penyusutan = new Date(form.tgl_mulai_penyusutan);
        }
        $scope.form.harga = form.harga_beli;
//        console.log(form)
    };
    
    /*
     * copy asset
     */
    $scope.copy = function () {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.is_disable = false;
        $scope.formtitle = master + " | Tambah Data";
        $scope.form.id = undefined;
        $scope.form.kode = undefined;
        $scope.form.no_serial = undefined;
        console.log($scope.form)
        Data.get('acc/m_asset/generateKode').then(function (response) {
            $scope.form.kode = response.data;
        });
    }
    
    /** save action */
    $scope.save = function (form) {
        Data.post(control_link + "/save", form).then(function (result) {
            if (result.status_code == 200) {
                if (result.data.is_penyusutan == 1) {
                    $scope.update_penyusutan(result.data.id);
                } else {
                    $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                    $scope.callServer(tableStateRef);
                    $scope.is_edit = false;
                }
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
            }
        });
    };
    $scope.update_penyusutan = function (id) {
        Data.get('acc/m_asset/getDetailPenyusutan', {id: id}).then(function (result) {
            $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
            $scope.callServer(tableStateRef);
            $scope.is_edit = false;
        });
    }
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
                    $rootScope.alert("Berhasil", "Data berhasil dihapus Permanen", "success");
                    $scope.cancel();
                });
            }
        });

    };


    $scope.detail_penyusutan = function (form) {
        var modalInstance = $uibModal.open({
            templateUrl: $rootScope.pathModulAcc + "tpl/m_asset/modal_detail_penyusutan.html",
            controller: "penyusutanCtrl",
            size: "lg",
            backdrop: "static",
            keyboard: false,
            resolve: {
                form: form,
            }
        });

        modalInstance.result.then(function (response) {
            if (response.data == undefined) {
            } else {
            }
        });
    }
});


app.controller("penyusutanCtrl", function ($state, $scope, Data, $uibModalInstance, form) {
    $scope.form = form;
    $scope.listDetail = [];

    $scope.getDetailPenyusutan = function (id) {

        Data.get('acc/m_asset/getDetailPenyusutan', {id: id}).then(function (result) {
            $scope.listDetail = result.data.list;
        });
    };

    $scope.getDetailPenyusutan(form.id);


    $scope.close = function () {
        $uibModalInstance.close({'data': undefined});
    };

});