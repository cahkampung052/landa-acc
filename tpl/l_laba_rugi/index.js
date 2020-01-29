app.controller('l_labarugiCtrl', function ($scope, Data, $rootScope, $uibModal, Upload, $state) {
    var control_link = "acc/l_laba_rugi";
    $scope.form = {};
    $scope.url = {};
    $scope.form.tanggal = {
        endDate: moment().add(1, 'M'),
        startDate: moment()
    };
    $scope.form.is_detail = 1;

    Data.get('site/base_url').then(function (response) {
        $scope.url = response.data;
    });
    /**
     * Ambil list lokasi
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
            m_lokasi_nama: $scope.form.m_lokasi_id.nama,
            startDate: moment($scope.form.tanggal.startDate).format('YYYY-MM-DD'),
            endDate: moment($scope.form.tanggal.endDate).format('YYYY-MM-DD'),
            is_detail: $scope.form.is_detail
        };
        if (is_export == 0 && is_print == 0) {
            Data.get(control_link + '/laporan', param).then(function (response) {
                if (response.status_code == 200) {
                    $scope.data = response.data.data;
                    $scope.detail = response.data.detail;
                    $scope.tampilkan = true;
                } else {
                    $scope.tampilkan = false;
                }
            });
        } else {
            Data.get('site/base_url').then(function (response) {
//                console.log(response)
                window.open(response.data.base_url + "api/acc/l_laba_rugi/laporan?" + $.param(param), "_blank");
            });
        }
    };

    $scope.viewBukuBesar = function (row) {
        console.log(row)
        var akun = {
            id : row.id,
            kode : row.kode,
            nama : row.nama2
        }
        var tanggal = $scope.form.tanggal;
        var akun = btoa(angular.toJson(akun))
        var tanggal = btoa(angular.toJson(tanggal))
        $state.go("laporan.buku_besar", {akun:akun, tanggal:tanggal}, {newtab : true})
    }

    /**
     * Modal setting pengecualian
     */
    $scope.modalSetting = function () {
        var modalInstance = $uibModal.open({
            templateUrl: $scope.url.base_url + "api/" + $scope.url.acc_dir + "/tpl/l_laba_rugi/modal.html",
            controller: "settingLabarugiCtrl",
            size: "md",
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

app.controller("settingLabarugiCtrl", function ($state, $scope, Data, $uibModalInstance, $rootScope) {

    $scope.listAkun = [];

    Data.get('acc/m_akun/getPengecualian').then(function (response) {
        $scope.listAkun = response.data.pengecualian_labarugi;
    });

    Data.get('acc/m_akun/akunDetail').then(function (data) {
        $scope.akunDetail = data.data.list;
    });
    /**
     * Tambah detail
     */
    $scope.addDetail = function (val) {
        var comArr = $(".tabletr").last().index() + 1
        var newDet = {
            m_akun_id: {
                id: $scope.akunDetail[0].id,
                kode: $scope.akunDetail[0].kode,
                nama: $scope.akunDetail[0].nama
            },
        };
        console.log(val)
        if (val != null) {
            val.splice(comArr, 0, newDet);
        } else {
            $scope.listAkun = [];
            $scope.listAkun[0] = {
                m_akun_id: {
                    id: $scope.akunDetail[0].id,
                    kode: $scope.akunDetail[0].kode,
                    nama: $scope.akunDetail[0].nama
                },
            }
        }

    };
    /**
     * Hapus detail
     */
    $scope.removeDetail = function (val, paramindex) {
        var comArr = eval(val);
        val.splice(paramindex, 1);
    };

    $scope.save = function () {

        var params = {
            type: "labarugi",
            data: $scope.listAkun
        }

        Data.post('acc/m_akun/savePengecualian', params).then(function (result) {
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