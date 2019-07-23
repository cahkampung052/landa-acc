app.controller("klasifikasiCtrl", function($scope, Data, $rootScope, Upload) {
    /**
     * Inialisasi
     */
    var tableStateRef = {};
    var control_link = "acc/m_klasifikasi";
    var master = "Master Klasifikasi Akun";
    $scope.master = master;
    $scope.displayed = [];
    $scope.form = {};
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.is_create = false;
    $scope.loading = false;
    /**
     * End inialisasi
     */
    /**
     * get list klasifikasi
     */
    $scope.getList = function() {
        Data.get(control_link + '/list').then(function(data) {
            $scope.parent = data.data.list;
            if ($scope.parent.length > 0 && $scope.is_create) {
                $scope.form.parent_id = $scope.parent[0].id;
                $scope.getakun($scope.form.parent_id);
            }
        });
    };
    /**
     * Get kode akun induk
     */
    $scope.getakun = function(id) {
        if (id > 0) {
            Data.get('acc/m_akun/getakun/' + id).then(function(data) {
                $scope.form.kode_induk = data.data.data.kode;
            });
        }
    };
    $scope.callServer = function callServer(tableState) {
        tableStateRef = tableState;
        var param = {};
        if (tableState.search.predicateObject) {
            param["filter"] = tableState.search.predicateObject;
        }
        Data.get(control_link + "/index", param).then(function(response) {
            $scope.displayed = response.data.list;
        });
    };
    /**
     * import
     */
    $scope.uploadFiles = function(file, errFiles) {
        $scope.f = file;
        $scope.errFile = errFiles && errFiles[0];
        if (file) {
            Data.get('site/url').then(function(data) {
                file.upload = Upload.upload({
                    url: data.data + 'acc/m_akun/import',
                    data: {
                        file: file
                    }
                });
                file.upload.then(function(response) {
                    var data = response.data;
                    if (data.status_code == 200) {
                        $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                        $scope.callServer(tableStateRef);
                    } else {
                        $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
                    }
                });
            });
        } else {
            $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
        }
    };
    /**
     * export
     */
    $scope.export = function() {
        window.location = 'api/acc/m_akun/export';
    };
    /**
     * create
     */
    $scope.create = function() {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.formtitle = master + " | Form Tambah Data";
        $scope.form = {};
        $scope.getList();
    };
    /** 
     * update
     */
    $scope.update = function(form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = false;
        $scope.formtitle = master + " | Edit Data : " + form.nama;
        $scope.getList();
        $scope.form = form;
        $scope.getakun(form.parent_id);
    };
    /** 
     * view
     */
    $scope.view = function(form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_create = false;
        $scope.formtitle = master + " | LIhat Data : " + form.nama;
        $scope.form = form;
    };
    /** 
     * save action
     */
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
    /** 
     * cancel action
     */
    $scope.cancel = function() {
        if (!$scope.is_view) {
            $scope.callServer(tableStateRef);
        }
        $scope.is_edit = false;
        $scope.is_view = false;
    };
    /**
     * Hapus
     */
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
                    if (result.status_code == 200) {
                        $rootScope.alert("Berhasil", "Data berhasil dihapus", "success");
                        $scope.cancel();
                    } else {
                        row.is_deleted = 0;
                        $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
                    }
                });
            }
        });
    };
    /**
     * Restore
     */
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
});