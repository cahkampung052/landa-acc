app.controller('akunCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/m_akun";
    var master = 'Master Akun';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;

    Data.get('acc/m_klasifikasi/list').then(function (response) {
        $scope.dataakun = response.data.list;
    });

    $scope.master = master;
    $scope.callServer = function callServer(tableState) {
        tableStateRef = tableState;
        $scope.isLoading = true;
        var offset = tableState.pagination.start || 0;
        var limit = tableState.pagination.number || 1000;
        /** set offset and limit */
        var param = {};
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
            $scope.base_url = response.data.base_url;
        });
        $scope.isLoading = false;
    };

    $scope.save_akun_kasir = function (val) {
        Data.post('acc/m_akun/save_akun_kasir', val).then(function (result) {
            if (result.status_code == 200) {
                toaster.pop('success', "Berhasil", "Data Berhasil Di Ubah");
            } else {
                toaster.pop('error', "Terjadi Kesalahan", "gagal mengubah Data");
            }
        });
    };
    $scope.getakun = function (id) {
        Data.get('acc/m_akun/getakun/' + id).then(function (data) {
            $scope.form.kode_induk = data.data.data.kode;
        });

    };
    /**import*/
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
                        $scope.cancel();
                    } else {
                        $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
                    }
                });
            });
        } else {
            toaster.pop('error', "Terjadi Kesalahan", result.errors);
        }
    };
    /**export*/
    $scope.export = function () {
        window.location = 'api/acc/m_akun/export';
    };
    /** create */
    $scope.create = function () {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.is_disable = false;
        $scope.formtitle = master + " | Form Tambah Data";
        $scope.form = {};
        $scope.form.is_kas = 0;
        $scope.form.saldo_normal = 1;
        
    };
    /** update */
    $scope.update = function (form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_update = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Edit Data : " + form.nama;
        $scope.form = form;
    };
    /** view */
    $scope.view = function (form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Lihat Data : " + form.nama;
        $scope.form = form;
    };
    /** save action */
    $scope.save = function (form) {
        var url = (form.id > 0) ? '/update' : '/create';
        Data.post(control_link + url, form).then(function (result) {
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
    $scope.modalBudget = function (form) {
        var modalInstance = $uibModal.open({
            templateUrl: "../acc-ukdc/api/acc/landaacc/tpl/m_akun/modal.html",
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
//
    if ($scope.listBudget.length == 0) {
        var thisYear = new Date();
        thisYear = thisYear.getFullYear();
        $scope.getBudget(thisYear);
        $scope.form.tahun = thisYear;
    }
//
    $scope.save = function () {
//        console.log($scope.form.tahun.toString().length)
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
                    $uibModalInstance.close({'data': result.data});
                } else {
                    $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
                }
            });
        }
    };

    $scope.close = function () {
        $uibModalInstance.close({'data': undefined});
    };

});
