app.controller('penyusutanassetCtrl', function ($scope, Data, $rootScope) {
    var tableStateRef;
    var control_link = "acc/m_asset";
    var master = 'Penyusutan Asset';
    $scope.formTitle = '';
    $scope.listDetail = [];
    $scope.base_url = '';
    $scope.is_show = false;
    $scope.filter = {};

    $scope.master = master;
    
    Data.get('acc/m_lokasi/index', {filter:{is_deleted:0}}).then(function (response) {
        $scope.listLokasi = response.data.list;
    });


    $scope.view = function(filter){
        if (filter.bulan==undefined) {
            $rootScope.alert("Terjadi Kesalahan", "Filter Bulan Tahun Harus diisi" ,"error");
            return;
        }
        if (filter.lokasi==undefined) {
            $rootScope.alert("Terjadi Kesalahan", "Filter Lokasi Harus diisi" ,"error");
            return;
        }

        var param = {
            bulan : moment(filter.bulan).format('YYYY-MM-DD'),
            lokasi_id : filter.lokasi.id 
        };

        Data.get(control_link+"/tampilPenyusutan", param).then(function (response) {
            $scope.listDetail = response.data.list;
            $scope.filter.total = response.data.total;
            $scope.is_show = true;
        });
    }

    $scope.prosesPenyusutan = function(){

        var data = {
            listDetail : $scope.listDetail,
            form : $scope.filter,
            bulan : moment($scope.filter.bulan).format('YYYY-MM-DD')
        };

        Data.post(control_link+"/prosesPenyusutan", data).then(function (result) {
            if (result.status_code == 200) {
                $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                $scope.reset();
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors) ,"error");
            }
        });
    }

    $scope.reset = function(){
        $scope.listDetail = [];
        $scope.filter = {};
        $scope.is_show = false;
    }

    
});