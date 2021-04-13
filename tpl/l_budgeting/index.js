app.controller('l_budgetingCtrl', function ($scope, Data, $rootScope, $uibModal) {
    var control_link = "acc/l_budgeting";
    $scope.form = {};
    $scope.is_group = false;
    $scope.form.tanggal = {
        endDate: moment().add(1, 'M'),
        startDate: moment()
    }

    Data.get('acc/m_akun/getAkunGroup').then(function (data) {
        $scope.is_group = data.data.is_group;
        if ($scope.is_group == true) {
            $scope.listAkunGroup = data.data.list;
        }
    });

    Data.get('acc/m_lokasi/getLokasi').then(function (response) {
        $scope.listLokasi = response.data.list;
        if ($scope.listLokasi.length > 0) {
            $scope.form.m_lokasi_id = $scope.listLokasi[0];
        }
    });
    /**
     * Ambil list semua akun
     */
    $scope.getAkunBebanPendapatan = function () {
        var params = {};
        params.m_akun_group_id = $scope.form.m_akun_group_id != undefined ? $scope.form.m_akun_group_id.id : null;

        Data.get('acc/m_akun/akunBebanPendapatan', params).then(function (data) {
            $scope.listAkun = data.data.list;
            if ($scope.listAkun.length > 0) {
                $scope.form.m_akun_id = $scope.listAkun[0];
            }
        });
    }
    $scope.getAkunBebanPendapatan()

    /**
     * Ambil laporan dari server
     */
    $scope.view = function (is_export, is_print) {
        var param = {
            export: is_export,
            print: is_print,
            m_akun_id: $scope.form.m_akun_id.id,
            m_lokasi_id: $scope.form.m_lokasi_id.id,
            nama_lokasi: $scope.form.m_lokasi_id.nama,
            startDate: moment($scope.form.tanggal.startDate).format('YYYY-MM-DD'),
            endDate: moment($scope.form.tanggal.endDate).format('YYYY-MM-DD'),
            m_akun_group_id: $scope.form.m_akun_group_id != undefined ? $scope.form.m_akun_group_id.id : null,
        };
        if (is_export == 0 && is_print == 0) {
            Data.get(control_link + '/laporan', param).then(function (response) {
                if (response.status_code == 200) {
                    $scope.data = response.data.data;
                    $scope.detail = response.data.detail;
                    $scope.tampilkan = true;
                } else {
                    $rootScope.alert("Terjadi Kesalahan", setErrorMessage(response.errors), "error");
                    $scope.tampilkan = false;
                }
            });
        } else {
            Data.get('site/base_url').then(function (response) {
                window.open(response.data.base_url + "api/acc/l_budgeting/laporan?" + $.param(param), "_blank");
            });
        }
    };
});