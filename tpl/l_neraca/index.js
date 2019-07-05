app.controller('l_neracaCtrl', function ($scope, Data, $rootScope) {
    var tableStateRef;
    var control_link = "acc/l_neraca";
    var master = 'Laporan Neraca';
    $scope.master = master;
    $scope.formTitle = '';
    $scope.base_url = '';
    $scope.form = {};
    $scope.form.tanggal = new Date();

    $scope.view = function (is_export, is_print) {
        $scope.tanggal = moment($scope.form.tanggal).format('DD-MM-YYYY');
        var param = {
            export: is_export,
            print: is_print,
            tanggal: moment($scope.form.tanggal).format('YYYY-MM-DD'),
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
            window.open("api/acc/l_buku_besar/laporan?" + $.param(param), "_blank");
        }
    };
});