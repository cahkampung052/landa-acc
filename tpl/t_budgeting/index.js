app.controller('budgetingCtrl', function ($scope, Data, $rootScope, $uibModal) {
    $scope.form = {};
    $scope.is_group = false;
    $scope.form.tahun = {
        locale: {
            format: 'DD-MMM-YYYY'
        },
        endDate: undefined,
        startDate: undefined
    };
    $scope.totalkredit = 0;
    $scope.tutup = false;

    Data.get('acc/m_akun/getAkunGroup').then(function (data) {
        $scope.is_group = data.data.is_group;
        if ($scope.is_group == true) {
            $scope.listAkunGroup = data.data.list;
            if ($scope.listAkunGroup != undefined && $scope.is_group == true) {
                $scope.form.m_akun_group_id = $scope.listAkunGroup[0];
                $scope.getAkunBebanPendapatan()
            }
        }

    })

    // Data.get('acc/apppengajuan/getKategori').then(function(response) {
    //     $scope.listKategori = response.data;
    // })
    Data.get('acc/m_akun/getTanggalSetting').then(function (data) {
        var tanggal = new Date(data.data.tanggal)
        tanggal.setDate(tanggal.getDate() - 1)
        $scope.form.tanggal = tanggal
    });
    /*
     * Ambil akun untuk detail
     */
    $scope.getAkunBebanPendapatan = function () {
        var params = {};
        if ($scope.form.m_akun_group_id != undefined) {
            params.m_akun_group_id = $scope.form.m_akun_group_id.id
        }

        Data.get('acc/m_akun/akunBebanPendapatan', params).then(function (data) {
            $scope.listAkun = data.data.list;
        });
    }
    $scope.getAkunBebanPendapatan();

    Data.get('acc/m_lokasi/getLokasi').then(function (response) {
        $scope.listLokasi = response.data.list;
        $scope.form.m_lokasi_id = $scope.listLokasi[0];
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
            angular.forEach(value.detail, function (v, k) {
//                console.log(v)
                totalbudget += parseInt(v.budget);
            })
        });
        $scope.totalbudget = totalbudget;
    };
    $scope.view = function (form) {
        if (form.m_lokasi_id !== undefined && form.start !== undefined && form.end !== undefined) {
            var param = {
                m_lokasi_id: form.m_lokasi_id.id,
                m_akun_group_id: form.m_akun_group_id != undefined ? form.m_akun_group_id.id : null,
                start: moment(form.start).format('YYYY-MM'),
                end: moment(form.end).format('YYYY-MM'),
                m_akun_id: form.m_akun_id != undefined ? form.m_akun_id.id : null,
                // m_kategori_pengajuan_id: form.m_kategori_pengajuan_id.id,
            };
            $scope.displayed = [];
            Data.get('acc/m_akun/getBudget', param).then(function (response) {
                $scope.data = response.data.data;
                $scope.displayed = response.data.detail;
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
});