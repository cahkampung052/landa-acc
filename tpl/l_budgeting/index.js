app.controller('l_budgetingCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var control_link = "acc/l_budgeting";
    $scope.form = {};
    $scope.form.tahun = new Date();

    Data.get('acc/m_lokasi/getLokasi').then(function (response) {
        $scope.listLokasi = response.data.list;
        if ($scope.listLokasi.length > 0) {
            $scope.form.m_lokasi_id = $scope.listLokasi[0];
        }
    });

    /**
     * Ambil list semua akun
     */
    Data.get('acc/m_akun/listakun').then(function (data) {
        $scope.listAkun = data.data;
        if ($scope.listAkun.length > 0) {
            $scope.form.m_akun_id = $scope.listAkun[0];
        }
    });
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
            tahun: moment($scope.form.tahun).format('YYYY')
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
//                console.log(response)
                window.open(response.data.base_url + "api/acc/l_budgeting/laporan?" + $.param(param), "_blank");
            });
        }
    };
});