app.controller('saldoawalCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
//    var control_link = "m_supplier";
    var master = 'Transaksi Saldo Awal';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.totaldebit = 0;
    $scope.form = {};
    $scope.totalkredit = 0;
//    $scope.form.m_fakultas_id = 1;

//    Data.get(control_link + '/cabang').then(function(data) {
//        $scope.cabang = data.data.data;
//    });

    $scope.sumTotal = function () {
        var totaldebit = 0;
        var totalkredit = 0;
        angular.forEach($scope.displayed, function (value, key) {
            totaldebit += value.debit;
            totalkredit += value.kredit;
        });
        $scope.totaldebit = totaldebit;
        $scope.totalkredit = totalkredit;
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
        
        Data.get('acc/m_lokasi/getLokasi', param).then(function (response) {
            $scope.listLokasi = response.data.list;
        });
        
//        Data.get('acc/m_akun/getSaldoAwal', param).then(function (response) {
//            $scope.displayed = response.data.detail;
//            $scope.form = {};
//        });
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
    
    $scope.setLokasi = function(lokasi){
        var param = {};
        param['m_lokasi_id'] = lokasi;
        Data.get('acc/m_akun/getSaldoAwal', param).then(function (response) {
            $scope.displayed = response.data.detail;
            $scope.sumTotal();
//            $scope.form = {};
        });
    }
    /** save action */
    $scope.save = function (form) {
        console.log(form)
        console.log($scope.displayed)
        var data = {
            form : form,
            detail : $scope.displayed
        }
        Data.post('acc/m_akun/saveSaldoAwal', data).then(function (result) {
            if (result.status_code == 200) {


                Swal.fire({
                    title: "Tersimpan",
                    text: "Data Berhasil Di Simpan.",
                    type: "success"
                }).then(function () {
                    $scope.callServer(tableStateRef);
                });
            } else {
                Swal.fire("Gagal", result.errors, "error");
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