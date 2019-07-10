app.controller('saldoawalpiutangCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
//    var control_link = "m_supplier";
    var master = 'Transaksi Saldo Awal Piutang';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.totaldebit = 0;
    $scope.form = {};
    $scope.totalkredit = 0;
//    $scope.form.m_fakultas_id = 1;

    Data.get('acc/m_lokasi/getLokasi').then(function (response) {
        $scope.listLokasi = response.data.list;
    });

    Data.get('acc/m_akun/akunDetail').then(function (response) {
        $scope.listAkun = response.data.list;
    });
    
    Data.get('acc/m_akun/getTanggalSetting').then(function(data) {
        console.log(data.data.tanggal)
        var tanggal = new Date(data.data.tanggal)
        tanggal.setDate(tanggal.getDate() - 1)
        $scope.form.tanggal = tanggal
        
    });

    $scope.sumTotal = function () {
        var total = 0;
        angular.forEach($scope.displayed, function (value, key) {
            total += value.total;
        });
        $scope.total = total;
    };

    $scope.master = master;
    $scope.callServer = function callServer(tableState) {
        tableStateRef = tableState;
        $scope.isLoading = true;
        var offset = tableState.pagination.start || 0;
        var limit = tableState.pagination.number || 1000;
        /** set offset and limit */
        var param = {};
        /** set sort and order */
        if (tableState.sort.predicate) {
            param['sort'] = tableState.sort.predicate;
            param['order'] = tableState.sort.reverse;
        }
        /** set filter */
        if (tableState.search.predicateObject) {
            param['filter'] = tableState.search.predicateObject;
        }
        
        
        
        $scope.isLoading = false;
    };

    $scope.view = function(form){
        if(form.tanggal != undefined && form.m_lokasi_id != undefined){
            console.log("ya")
            var param = {};
            param['m_lokasi_id'] = form.m_lokasi_id;
            param['tanggal'] = moment(form.tanggal).format('YYYY-MM-DD');
            Data.post('acc/t_saldo_awal_piutang/getPiutangAwal', param).then(function (response) {
                console.log(response)
                $scope.displayed = response.data.detail;
                $scope.form.tanggal = new Date(response.data.tanggal);
                angular.forEach($scope.displayed, function (value, key) {
                    if(value.m_akun_id === undefined){
                        value.m_akun_id = $scope.listAkun[0]
                        value.total = 0;
                    }
                    
                });
                $scope.sumTotal();
            });
        }else{
            console.log("tidak")
        }
        
    }
    /** save action */
    $scope.save = function (form) {
        console.log(form)
        console.log($scope.displayed)
        var data = {
            form : form,
            detail : $scope.displayed
        }
        if(form.tanggal != undefined && form.m_lokasi_id != undefined){
            Data.post('acc/t_saldo_awal_piutang/savePiutang', data).then(function (result) {
                if (result.status_code == 200) {


                    $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                    $scope.getHutang(form)
                } else {
                    $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors) ,"error");
                }
            });
        }else{
            $rootScope.alert("Terjadi Kesalahan", "Tanggal dan lokasi harus diisi" ,"error");
        }
        
    };
    /** cancel action */
    $scope.cancel = function () {
        if (!$scope.is_view) {
            $scope.callServer(tableStateRef);
        }
        $scope.is_edit = false;
        $scope.is_view = false;
    };
});