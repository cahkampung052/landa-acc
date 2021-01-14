app.controller('l_aruskascCtrl', function ($scope, Data, $rootScope, $uibModal) {
    var control_link = "acc/l_arus_kas_custom";
    $scope.form = {};
    $scope.form.tanggal = {
        endDate: moment().add(1, 'M'),
        startDate: moment()
    };

    Data.get('site/base_url').then(function (response) {
        $scope.url = response.data;
    });
    /*
     * ambil lokasi
     */
    Data.get('acc/m_lokasi/getLokasi').then(function (response) {
        $scope.listLokasi = response.data.list;
        if ($scope.listLokasi.length > 0) {
            $scope.form.m_lokasi_id = $scope.listLokasi[0];
        }
    });
    /**
     * Ambil laporan dari server
     */
    $scope.view = function (is_export, is_print) {
        $scope.mulai = moment($scope.form.tanggal.startDate).format('DD-MM-YYYY');
        $scope.selesai = moment($scope.form.tanggal.endDate).format('DD-MM-YYYY');
        var param = {
            export: is_export,
            print: is_print,
            m_lokasi_id: $scope.form.m_lokasi_id.id,
            nama_lokasi: $scope.form.m_lokasi_id.nama,
            startDate: moment($scope.form.tanggal.startDate).format('YYYY-MM-DD'),
            endDate: moment($scope.form.tanggal.endDate).format('YYYY-MM-DD'),
        };
        if (is_export == 0 && is_print == 0) {
            Data.get(control_link + '/laporan', param).then(function (response) {
                console.log(response)
                if (response.status_code == 200) {
                    $scope.data = response.data.data;
                    $scope.detail = response.data.detail;
                    console.log($scope.detail)
                    $scope.tampilkan = true;
                } else {
                    $scope.tampilkan = false;
                }
            });
        } else {
            Data.get('site/base_url').then(function (response) {
//                console.log(response)
                window.open(response.data.base_url + "api/acc/l_arus_kas_custom/laporan?" + $.param(param), "_blank");
            });
        }
    };

    $scope.updateAkun = function (akun) {
        var params = {tipe_arus: akun.tipe_arus, id: akun.id, is_deleted: 0};

        Data.post("acc/m_akun/trash", params).then(function (response) {
            if (response.status_code == 200) {
                $scope.view(0, 0);
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(response.errors), "error");
            }
        });
    }

    /**
     * Modal setting pengecualian
     */
    $scope.modalSetting = function () {
        var modalInstance = $uibModal.open({
            templateUrl: $scope.url.base_url + "api/" + $scope.url.acc_dir + "/tpl/l_arus_kas_custom/modal.html",
            controller: "settingcustomCtrl",
            size: "xl",
            backdrop: "static",
            keyboard: false,
        });
        modalInstance.result.then(function (response) {
            if (response.data == undefined) {
            } else {
            }
        });
    }
});

app.controller("settingcustomCtrl", function ($state, $scope, Data, $uibModalInstance, $rootScope) {

    Data.get('acc/l_arus_kas_custom/getSetting').then(function (response) {
        if (response.data.status) {
            $scope.listSetting = response.data.data;
        } else {
            $scope.listSetting = [
                {
                    tipe: 'ARUS KAS DARI KEGIATAN OPEARSIONAL',
                    detail: [{}],
                },
                {
                    tipe: 'ARUS KAS DARI KEGIATAN INVESTASI',
                    detail: [{}],
                },
                {
                    tipe: 'ARUS KAS DARI AKTIVITAS PENDANAAN',
                    detail: [{}],
                },
            ];
        }
    });

    Data.get('acc/m_akun/getPengecualian').then(function (response) {
        $scope.listAkun = response.data.pengecualian_neraca;
    });

    Data.get('acc/m_akun/akunAll').then(function (data) {
        $scope.akunDetail = data.data.list;
    });

    $scope.addDetail = function (detail) {
        console.log(detail)
        var val = detail.length;
        var newDet = {};
        detail.push(newDet);
    };
    $scope.removeDetail = function (detail, paramindex) {
        var r = confirm('Apakah Anda ingin menghapus item ini?');
        if (r) {
            detail.splice(paramindex, 1);
//                $scope.prepareJurnal();
        }
    };

    $scope.save = function () {

        var params = {
            form: $scope.listSetting,
        }

        Data.post('acc/l_arus_kas_custom/saveSetting', params).then(function (result) {
            if (result.status_code == 200) {
                $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                $uibModalInstance.close({
                    'data': result.data
                });
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
            }
        });
    };
    $scope.close = function () {
        $uibModalInstance.close({
            'data': undefined
        });
    };
});
        