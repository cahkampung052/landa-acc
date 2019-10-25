app.controller('akunCtrl', function ($scope, Data, $rootScope, $uibModal, Upload, $state) {
    var tableStateRef;
    var control_link = "acc/m_akun";
    var master = 'Master Akun';
    $scope.displayed = [];
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.is_create = false;
    /**
     * Ambil klasifikasi
     */
    $scope.getAkun = function (tipe) {
        Data.get('acc/m_akun/getByType', {tipe: tipe}).then(function (response) {
            $scope.dataakun = response.data.list;
        });
    };
    /**
     * Tampilkan akun di index
     */
    $scope.callServer = function callServer(tableState) {
        tableStateRef = tableState;
        $scope.isLoading = true;
        var param = {};
        /** set filter */
        if (tableState.search.predicateObject) {
            param['filter'] = tableState.search.predicateObject;
        }
        Data.get(control_link + '/index', param).then(function (response) {
            $scope.displayed = response.data.list;
        });
        $scope.isLoading = false;
    };
    /**
     * Ambil akun
     */
    $scope.getakun = function (id) {
        Data.get('acc/m_akun/getakun/' + id).then(function (data) {
            $scope.form.kode_induk = data.data.data.kode;
        });
    };
    /**
     * import
     */
    $scope.uploadFiles = function (file, errFiles) {
        $scope.f = file;
        $scope.errFile = errFiles && errFiles[0];
        if (file) {
            Data.get('site/url').then(function (data) {
                file.upload = Upload.upload({
                    url: data.data + 'acc/m_akun/import',
                    data: {
                        file: file
                    }
                });
                file.upload.then(function (response) {
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
    $scope.export = function () {
        window.location = 'api/acc/m_akun/export';
    };
    /** 
     * create
     */
    $scope.create = function () {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.formtitle = master + " | Form Tambah Data";
        $scope.form = {};
        $scope.form.is_kas = 0;
        $scope.form.saldo_normal = 1;
        $scope.form.is_tipe = 1;
        $scope.form.is_induk = 1;
    };
    /** 
     * update
     */
    $scope.update = function (form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = false;
        $scope.formtitle = master + " | Edit Data : " + form.nama;
        $scope.form = form;
        $scope.getAkun(form.tipe);
    };
    /** 
     * view
     */
    $scope.view = function (form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_create = false;
        $scope.formtitle = master + " | Lihat Data : " + form.nama;
        $scope.form = form;
    };

    $scope.viewBukuBesar = function (row) {
        console.log(row)
        var akun = {
            id: row.id,
            kode: row.kode,
            nama: row.nama
        }
        var akun = btoa(angular.toJson(akun))

        var url = $state.href('laporan.buku_besar', {akun: akun});
        window.open(url, '_blank');
    }

    /** 
     * save action
     */
    $scope.save = function (form) {
        Data.post(control_link + '/save', form).then(function (result) {
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
    $scope.cancel = function () {
        if (!$scope.is_view) {
            $scope.callServer(tableStateRef);
        }
        $scope.is_edit = false;
        $scope.is_view = false;
    };
    /**
     * Hapus akun
     */
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
    /**
     * Restore akun
     */
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
    /**
     * Modal budgetting
     */
    $scope.modalBudget = function (form) {
        var modalInstance = $uibModal.open({
            templateUrl: $rootScope.pathModulAcc + "tpl/m_akun/modal.html",
            controller: "budgetCtrl",
            size: "md",
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
app.controller("budgetCtrl", function ($state, $scope, Data, $uibModalInstance, form, $rootScope) {
    $scope.form = form;
    $scope.listBudget = [];
    $scope.getBudget = function (tahun) {
        var param = {
            tahun: tahun,
            m_akun_id: $scope.form.id
        };
        if (tahun.toString().length > 3) {
            Data.get('acc/m_akun/getBudget', param).then(function (result) {
                $scope.listBudget = result.data;
            });
        }
    }
    if ($scope.listBudget.length == 0) {
        var thisYear = new Date();
        var thisYear2 = thisYear.getFullYear();
        $scope.getBudget(thisYear2);
        $scope.form.tahun = thisYear;
    }
    $scope.save = function () {
        if ($scope.form.tahun.toString().length < 4) {
            $rootScope.alert("Terjadi Kesalahan", "Anda harus mengisi tahun dengan benar", "error");
        } else {
            var params = {
                listBudget: $scope.listBudget,
                form: $scope.form
            };
            Data.post('acc/m_akun/saveBudget', params).then(function (result) {
                if (result.status_code == 200) {
                    $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                    $uibModalInstance.close({
                        'data': result.data
                    });
                } else {
                    $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
                }
            });
        }
    };
    $scope.close = function () {
        $uibModalInstance.close({
            'data': undefined
        });
    };
});