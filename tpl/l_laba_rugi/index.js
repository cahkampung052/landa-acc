app.controller('l_labarugiCtrl', function($scope, Data, $rootScope, $uibModal, Upload) {
    var control_link = "acc/l_laba_rugi";
    $scope.form = {};
    $scope.form.tanggal = {
        endDate: moment().add(1, 'M'),
        startDate: moment()
    };
    /**
     * Ambil list lokasi
     */
    Data.get('acc/m_lokasi/getLokasi').then(function(response) {
        $scope.listLokasi = response.data.list;
        if ($scope.listLokasi.length > 0) {
            $scope.form.m_lokasi_id = $scope.listLokasi[0];
        }
    });
    /**
     * Ambil laporan dari server
     */
    $scope.view = function(is_export, is_print) {
        $scope.mulai = moment($scope.form.tanggal.startDate).format('DD-MM-YYYY');
        $scope.selesai = moment($scope.form.tanggal.endDate).format('DD-MM-YYYY');
        var param = {
            export: is_export,
            print: is_print,
            m_lokasi_id: $scope.form.m_lokasi_id.id,
            m_lokasi_nama: $scope.form.m_lokasi_id.nama,
            startDate: moment($scope.form.tanggal.startDate).format('YYYY-MM-DD'),
            endDate: moment($scope.form.tanggal.endDate).format('YYYY-MM-DD'),
        };
        if (is_export == 0 && is_print == 0) {
            Data.get(control_link + '/laporan', param).then(function(response) {
                if (response.status_code == 200) {
                    $scope.data = response.data.data;
                    $scope.detail = response.data.detail;
                    $scope.tampilkan = true;
                    $scope.totalsemua = ($scope.detail[0].total - $scope.detail[1].total - $scope.detail[2].total - $scope.detail[3].total + $scope.detail[4].total - $scope.detail[5].total);
                } else {
                    $scope.tampilkan = false;
                }
            });
        } else {
            window.open("api/acc/l_laba_rugi/laporan?" + $.param(param), "_blank");
        }
    };
});