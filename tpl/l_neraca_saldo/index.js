app.controller('l_neracasaldoCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/l_neraca_saldo";
    var master = 'Laporan Neraca Saldo';
    $scope.master = master;
    $scope.formTitle = '';
    $scope.base_url = '';
    $scope.form = {};

//    Data.get(control_link + '/cabang').then(function(data) {
//        $scope.cabang = data.data.data;
//    });

    $scope.form = {};
    $scope.form.tanggal = {endDate: moment().add(1, 'M'), startDate: moment()};
    

    $scope.view = function (form) {
        Data.post(control_link + '/laporan', form).then(function (response) {
            if (response.status_code == 200) {
                $scope.data = response.data.data;
                $scope.detail = response.data.detail;
                $scope.tampilkan = true;
            } else {
                $scope.tampilkan = false;
//                toaster.pop('error', "Terjadi Kesalahan", setErrorMessage(response.errors));
            }
        });
    };
    
    $scope.exportExcel = function (form){
        form.tanggal.endDate = moment(form.tanggal.endDate).format('YYYY-MM-DD');
        form.tanggal.startDate = moment(form.tanggal.startDate).format('YYYY-MM-DD');
       
        window.location = "api/acc/l_neraca_saldo/exportExcel?" + $.param(form);
    }

    $scope.resetAkun = function () {
        $scope.form.akun_id = undefined;
    }

//    $scope.resetSubUnit = function () {
//        $scope.form.m_unker = undefined;
//    }

    $scope.resetUnit = function () {
        $scope.form.unit = undefined;
        $scope.getCabang = $scope.allCabang;
    }

});