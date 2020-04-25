app.controller('supplierCtrl', function($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/m_supplier";
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.generateKode = function() {
        Data.get(control_link + '/kode').then(function(response) {
            $scope.form.kode = response;
        });
    }
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
        Data.get(control_link + '/index', param).then(function(response) {
            $scope.displayed = response.data.list;
            tableState.pagination.numberOfPages = Math.ceil(response.data.totalItems / limit);
        });
        $scope.isLoading = false;
    };
    /** create */
    $scope.create = function() {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.is_disable = false;
        $scope.formtitle = "Data Supplier | Form Tambah Data";
        $scope.form = {};
        $scope.generateKode();
    };
    /** update */
    $scope.update = function(form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_update = true;
        $scope.is_disable = true;
        $scope.formtitle = "Data Supplier | Edit Data : " + form.nama;
        $scope.form = form;
        if (!$scope.form.kode) {
            $scope.generateKode();
        }
    };
    /** view */
    $scope.view = function(form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_disable = true;
        $scope.formtitle = "Data Supplier | Lihat Data : " + form.nama;
        $scope.form = form;
    };
    /** save action */
    $scope.save = function(form) {
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
                $rootScope.alert("Berhasil", "Data berhasil direstore", "success");
                $scope.cancel();
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