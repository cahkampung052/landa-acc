app.controller('l_neracasaldoCtrl', function ($scope, Data, $rootScope, $uibModal, Upload, $state) {
    var control_link = "acc/l_neraca_saldo";
    $scope.form = {};
    $scope.is_group = false;
    $scope.form.tanggal = {
        endDate: moment().add(1, 'M'),
        startDate: moment()
    };

    Data.get('acc/m_akun/getAkunGroup').then(function (data) {
        $scope.is_group = data.data.is_group;
        if ($scope.is_group == true) {
            $scope.listAkunGroup = data.data.list;
        }
    })

    Data.get('acc/m_lokasi/getLokasi').then(function (response) {
        $scope.listLokasi = response.data.list;
        if ($scope.listLokasi.length > 0) {
            $scope.form.m_lokasi_id = $scope.listLokasi[0];
        }
    });
    /**
     * Ambil data dari server
     */
    $scope.view = function (is_export, is_print) {
        $scope.mulai = moment($scope.form.tanggal.startDate).format('DD-MM-YYYY');
        $scope.selesai = moment($scope.form.tanggal.endDate).format('DD-MM-YYYY');
        var param = {
            export: is_export,
            print: is_print,
            startDate: moment($scope.form.tanggal.startDate).format('YYYY-MM-DD'),
            endDate: moment($scope.form.tanggal.endDate).format('YYYY-MM-DD'),
            m_lokasi_id: $scope.form.m_lokasi_id != undefined ? $scope.form.m_lokasi_id.id : null,
            nama_lokasi: $scope.form.m_lokasi_id != undefined ? $scope.form.m_lokasi_id.nama : null,
            m_akun_group_id: $scope.form.m_akun_group_id != undefined ? $scope.form.m_akun_group_id.id : null,
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
                window.open(response.data.base_url + "api/acc/l_neraca_saldo/laporan?" + $.param(param), "_blank");
            });
        }
    };

    $scope.viewBukuBesar = function (row) {
        console.log(row)
        var akun = {
            id: row.id,
            kode: row.kode,
            nama: row.nama
        }
        var akun = btoa(angular.toJson(akun))
        console.log(akun)
        $state.go("laporan.buku_besar", {akun: akun})
//        $state.go("backOffice.keuangan.laporan.jurnal_umum")
    }
});