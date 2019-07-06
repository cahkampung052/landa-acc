app.controller('l_budgetingCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/l_budgeting";
    var master = 'Laporan Budgeting';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.form = {};
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.form.tahun = new Date();
    $scope.master = master;
    
    /**
     * Ambil list semua akun
     */
    Data.get('acc/m_akun/listakun').then(function(data) {
        $scope.listAkun = data.data;
        if ($scope.listAkun.length > 0) {
            $scope.form.m_akun_id = $scope.listAkun[0];
        }
    });

    /**
     * Ambil laporan dari server
     */
    $scope.view = function(is_export, is_print) {
        
        var param = {
            export: is_export,
            print: is_print,
            m_akun_id: $scope.form.m_akun_id.id,
            tahun: moment($scope.form.tahun).format('YYYY')
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
            window.open("api/acc/l_budgeting/laporan?" + $.param(param), "_blank");
        }
    };
    
});