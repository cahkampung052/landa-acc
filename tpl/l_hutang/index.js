app.controller('l_hutangCtrl', function($scope, Data, $rootScope, $stateParams) {
    var control_link = "acc/l_hutang";
    $scope.formTitle = '';
    $scope.form = {};
    $scope.form.m_kontak_id = {
        id: 0,
        nama: "Semua Supplier"
    }
    $scope.form.tanggal = {
        endDate: moment().add(1, 'M'),
        startDate: moment()
    };
    /**
     * Ambil list semua akun
     */
    $scope.listAkun = [{'id' : 0, 'nama': 'SEMUA HUTANG'}];
    Data.get('acc/m_akun/akunHutang').then(function(data) {
        $scope.listAkun.push(data.data.list);
        if ($scope.listAkun.length > 0) {
            $scope.form.m_akun_id = $scope.listAkun[0];
        }
    });
    /**
     * Ambil semua lokasi
     */
    Data.get('acc/m_lokasi/getLokasi').then(function(response) {
        $scope.listLokasi = response.data.list;
        if ($scope.listLokasi.length > 0) {
            $scope.form.m_lokasi_id = $scope.listLokasi[0];
            if (typeof $stateParams.param != undefined) {
                var param = angular.fromJson(atob($stateParams.param));
                $scope.form.m_akun_id = param.m_akun_id;
                $scope.form.m_lokasi_id = param.m_lokasi_id;
                $scope.form.m_kontak_id = param.m_kontak_id;
                $scope.form.tanggal = {
                    endDate: new Date(param.endDate),
                    startDate: new Date(param.endDate)
                };
                $scope.view(0, 0);
            }
        }
    });
    /*
     * ambil supplier
     */
    $scope.getSupplier = function(val) {
        Data.get('acc/m_supplier/getSupplier', {
            nama: val
        }).then(function(response) {
            $scope.listSupplier = response.data.list;
            if ($scope.listSupplier.length > 0 && $scope.form.m_kontak_id.id == 0) {
                $scope.form.m_kontak_id = $scope.listSupplier[0];
            }
        });
    }
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
            m_kontak_id: $scope.form.m_kontak_id.id,
            nama_lokasi: $scope.form.m_lokasi_id.nama,
            m_akun_id: $scope.form.m_akun_id.id,
            startDate: moment($scope.form.tanggal.startDate).format('YYYY-MM-DD'),
            endDate: moment($scope.form.tanggal.endDate).format('YYYY-MM-DD'),
        };
        if (is_export == 0 && is_print == 0) {
            Data.get(control_link + '/laporan', param).then(function(response) {
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
            Data.get('site/base_url').then(function(response) {
                window.open(response.data.base_url + "api/acc/l_hutang/laporan?" + $.param(param), "_blank");
            });
        }
    };
});