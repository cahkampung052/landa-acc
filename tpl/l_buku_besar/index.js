app.controller('l_bukubesarCtrl', function ($scope, Data, $rootScope, $stateParams) {
    var control_link = "acc/l_buku_besar";
    $scope.form = {};
    $scope.form.tanggal = {
        endDate: moment().add(1, 'M'),
        startDate: moment()
    };
    /**
     * Ambil list semua akun
     */
    Data.get('acc/m_akun/listakun').then(function (data) {
        $scope.listAkun = data.data;
        if ($scope.listAkun.length > 0 && typeof $stateParams.akun == undefined) {
            $scope.form.m_akun_id = $scope.listAkun[0];
        }
    });
    
    Data.get('site/base_url').then(function (response) {
        $.getJSON(response.data.base_url + "/data.json", function (json) {
            angular.forEach(json, function (val, key) {
                $scope[key] = val;
            })
        });
    });
    /**
     * Ambil semua lokasi
     */
    Data.get('acc/m_lokasi/getLokasi').then(function (response) {
        $scope.listLokasi = response.data.list;
        if ($scope.listLokasi.length > 0) {
            $scope.form.m_lokasi_id = $scope.listLokasi[0];
            if (typeof $stateParams.akun != undefined) {
                var akun = angular.fromJson(atob($stateParams.akun));
                $scope.form.m_akun_id = akun;
                $scope.form.tanggal = {
                    endDate: moment(),
                    startDate: moment().subtract(2, 'M')
                };
                $scope.view(0, 0);
            }
            if (typeof $stateParams.tanggal != undefined) {
                var tanggal = angular.fromJson(atob($stateParams.tanggal));
                $scope.form.tanggal = tanggal;
                $scope.view(0, 0);
            }
            
            
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
            m_akun_id: $scope.form.m_akun_id.id,
            startDate: moment($scope.form.tanggal.startDate).format('YYYY-MM-DD'),
            endDate: moment($scope.form.tanggal.endDate).format('YYYY-MM-DD'),
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
                window.open(response.data.base_url + "api/acc/l_buku_besar/laporan?" + $.param(param), "_blank");
            });
        }
    };


});