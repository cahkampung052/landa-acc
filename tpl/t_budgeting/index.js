app.controller('budgetingCtrl', function ($scope, Data, $rootScope, $uibModal) {
    $scope.form = {};
    $scope.form.tahun = {
        locale: {
            format: 'DD-MMM-YYYY'
        },
        endDate: undefined,
        startDate: undefined
    };
    $scope.totalkredit = 0;
    $scope.tutup = false;
    Data.get('acc/m_akun/getTanggalSetting').then(function (data) {
        var tanggal = new Date(data.data.tanggal)
        tanggal.setDate(tanggal.getDate() - 1)
        $scope.form.tanggal = tanggal
    });

    Data.get("/acc/apppengajuan/getKategori").then(function (response) {
        $scope.listKategori = response.data;
    });

    /*
     * Ambil akun untuk detail
     */
    Data.get('acc/m_akun/akunBeban').then(function (data) {
        $scope.listAkun = data.data.list;
    });
    Data.get('acc/m_lokasi/getLokasi').then(function (response) {
        $scope.listLokasi = response.data.list;
    });
    Data.get("acc/t_tutup_bulan/index", {
        filter: {
            jenis: "bulan"
        }
    }).then(function (response) {
        if (response.data.list.length > 0) {
            $scope.tutup = true;
        }
    });
    $scope.sumTotal = function () {
        var totalbudget = 0;
        angular.forEach($scope.displayed, function (value, key) {
            totalbudget += parseInt(value.detail.budget);
        });
        $scope.totalbudget = totalbudget;
    };
    $scope.view = function (form) {
        if (form.m_lokasi_id !== undefined && form.tanggal !== undefined && form.m_akun_id !== undefined) {
            var param = {
                m_lokasi_id: form.m_lokasi_id.id,
                start: moment(form.start).format('YYYY-MM'),
                end: moment(form.end).format('YYYY-MM'),
                m_akun_id: form.m_akun_id.id,
                m_kategori_pengajuan_id : form.m_kategori_pengajuan_id.id,
            };
            $scope.displayed = [];
            Data.get('acc/m_akun/getBudget', param).then(function (response) {
                $scope.displayed = response.data;
                $scope.sumTotal();
            });
        }
    }
    /** save action */
    $scope.save = function (form) {
        form['tahun'] = moment(form.tahun).format('YYYY');
        var data = {
            form: form,
            detail: $scope.displayed
        }
        Data.post('acc/m_akun/saveBudget', data).then(function (result) {
            if (result.status_code == 200) {
                $rootScope.alert("Success", "Data berhasil disimpan", "success");
                $scope.form.tahun = new Date(form.tanggal)
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
    /*
     * export format
     */
    $scope.export = function () {
        window.location = 'api/acc/m_akun/exportSaldoAwal';
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
                    url: data.data + 'acc/m_akun/importSaldoAwal',
                    data: {
                        file: file
                    }
                });
                file.upload.then(function (response) {
                    var data = response.data.data;
                    if (response.data.status_code == 200) {
                        $scope.displayed = data.detail;
                        $scope.sumTotal();
                        $scope.form.tanggal = new Date(data.data.tanggal);
                        $scope.form.m_lokasi_id = data.data.lokasi;
                    } else {
                        $rootScope.alert("Terjadi Kesalahan", setErrorMessage(response.errors), "error");
                    }
                });
            });
        } else {
            $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
        }
    };
});