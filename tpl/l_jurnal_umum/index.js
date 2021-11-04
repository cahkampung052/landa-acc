app.controller('l_jurnalumumCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var control_link = "acc/l_jurnal_umum";
    $scope.form = {};
    $scope.form.tanggal = {
        endDate: moment().add(1, 'M'),
        startDate: moment()
    };
    /**
     * Ambil list transaksi
     */
    $scope.listTransaksi = [{'id': 0, 'nama': 'SEMUA TRANSAKSI'}];
    Data.get('acc/l_jurnal_umum/getTransaksi').then(function (response) {
        angular.forEach(response.data, function (value, key) {
            $scope.listTransaksi.push(value);
        });
        if ($scope.listTransaksi.length > 0) {
            $scope.form.m_transaksi_id = $scope.listTransaksi[0];
        }
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
     * Ambil list Grup
     */
    $scope.listAkunGrup = [{'id': 0, 'nama': 'SEMUA GRUP AKUN'}];
    Data.get('acc/l_jurnal_umum/getAkunGrup').then(function (response) {
        angular.forEach(response.data, function (value, key) {
            $scope.listAkunGrup.push(value);
        });
        if ($scope.listAkunGrup.length > 0) {
            $scope.form.m_akun_group_id = $scope.listAkunGrup[0];
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
            m_transaksi_id: $scope.form.m_transaksi_id.id,
            m_lokasi_id: $scope.form.m_lokasi_id.id,
            m_akun_group_id: $scope.form.m_akun_group_id.id,
            nama_lokasi: $scope.form.m_lokasi_id.nama,
            startDate: moment($scope.form.tanggal.startDate).format('YYYY-MM-DD'),
            endDate: moment($scope.form.tanggal.endDate).format('YYYY-MM-DD'),
            status: $scope.form.status,
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
                window.open(response.data.base_url + "api/acc/l_jurnal_umum/laporan?" + $.param(param), "_blank");
            });
        }
    };
});
