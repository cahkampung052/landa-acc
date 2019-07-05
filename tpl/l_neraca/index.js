app.controller('l_neracaCtrl', function($scope, Data, $rootScope) {
    var tableStateRef;
    var control_link = "acc/l_neraca";
    var master = 'Laporan Neraca';
    $scope.master = master;
    $scope.formTitle = '';
    $scope.base_url = '';
    $scope.form = {};
    $scope.form.tanggal = new Date();
    $scope.view = function(form) {
        Data.post(control_link + '/laporan', form).then(function(response) {
            if (response.status_code == 200) {
                $scope.data = response.data;
                $scope.data.tanggal = response.data.tanggal;
                $scope.data.disiapkan = response.data.disiapkan;
                $scope.tampilkan = true;
            } else {
                $scope.tampilkan = false;
            }
        });
    };
});