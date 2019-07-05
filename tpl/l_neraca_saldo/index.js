app.controller('l_neracasaldoCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/l_neraca_saldo";
    var master = 'Laporan Neraca Saldo';
    $scope.master = master;
    $scope.formTitle = '';
    $scope.base_url = '';
    $scope.form = {};

    $scope.form = {};
    $scope.form.tanggal = {endDate: moment().add(1, 'M'), startDate: moment()};


    $scope.view = function (is_export, is_print) {
        $scope.mulai = moment($scope.form.tanggal.startDate).format('DD-MM-YYYY');
        $scope.selesai = moment($scope.form.tanggal.endDate).format('DD-MM-YYYY');
        var param = {
            export: is_export,
            print: is_print,
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
                    $scope.tampilkan = false;
                }
            });
        } else {
            window.open("api/acc/l_neraca_saldo/laporan?" + $.param(param), "_blank");
        }

    };


});