app.controller('saldoawalhutangCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
//    var control_link = "m_supplier";
    var master = 'Transaksi Saldo Awal Hutang';
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

    Data.get('acc/m_akun/akunHutang').then(function (response) {
        $scope.listAkun = response.data.list;
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

    /** create */
    $scope.create = function () {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.is_disable = false;
        $scope.formtitle = master + " | Form Tambah Data";
        $scope.form = {};
        $scope.form.parent_id = '0';
    };
    /** update */
    $scope.update = function (form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_update = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Edit Data : " + form.nama;
        $scope.form = form;
    };
    /** view */
    $scope.view = function (form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Lihat Data : " + form.nama;
        $scope.form = form;
    };
    
    $scope.getHutang = function(form){
        console.log(form);
        if(form.tanggal != undefined && form.m_lokasi_id != undefined){
            console.log("ya")
            var param = {};
            param['m_lokasi_id'] = form.m_lokasi_id;
            param['tanggal'] = moment(form.tanggal).format('YYYY-MM-DD');
            Data.get('acc/t_saldo_awal_hutang/getHutangAwal', param).then(function (response) {
                $scope.displayed = response.data.detail;
                $scope.sumTotal();
    //            $scope.form = {};
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
        Data.post('acc/t_saldo_awal_hutang/saveHutang', data).then(function (result) {
            if (result.status_code == 200) {


                $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                $scope.getHutang(form)
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors) ,"error");
            }
        });
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