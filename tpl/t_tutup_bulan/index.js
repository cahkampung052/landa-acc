app.controller('tutupbulanCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/t_tutup_bulan";
    var master = 'Transaksi Tutup Bulan';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.form = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;
    
    /*
     * untuk permission button
     */
    $scope.permission = 0;
    
    $scope.getNeracaSaldo = function (date = null) {
        if(date == null){
            var date = new Date();
        }else{
            var date = new Date(date)
        }
        var firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
        var param = {
            export: 0,
            print: 0,
            startDate: moment(firstDay).format('YYYY-MM-DD'),
            endDate: moment(date).format('YYYY-MM-DD'),
        };
        Data.get('acc/l_neraca_saldo/laporan', param).then(function (response) {
            if (response.status_code == 200) {
                $scope.form.bulan = date;
                $scope.data = response.data.data;
                $scope.detail = response.data.detail;
                $scope.tampilkan = true;
            } else {
                $scope.tampilkan = false;
            }
        });
    }

    $scope.master = master;
    $scope.callServer = function callServer(tableState) {
        tableStateRef = tableState;
        $scope.isLoading = true;
        var offset = tableState.pagination.start || 0;
        var limit = tableState.pagination.number || 1000;
        /** set offset and limit */
        var param = {
            offset : offset, 
            limit : limit
        };
        /** set sort and order */
        if (tableState.sort.predicate) {
            param['sort'] = tableState.sort.predicate;
            param['order'] = tableState.sort.reverse;
        }
        /** set filter */
        if (tableState.search.predicateObject) {
            param['filter'] = tableState.search.predicateObject;
        }
        
        Data.get(control_link + '/index', param).then(function (response) {
            $scope.displayed = response.data.list;
            if($scope.displayed.length > 1){
                $scope.permission = 1;
            }
            $scope.base_url = response.data.base_url;
            tableState.pagination.numberOfPages = Math.ceil(
                    response.data.totalItems / limit
                    );
        });
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
        $scope.form.bulan = new Date();
        $scope.listDetail = [{}];
        console.log($scope.form)
        $scope.getNeracaSaldo();
    };
    
    /** view */
    $scope.view = function (form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Lihat Data : " + form.bln_tahun;
        console.log(form)
        $scope.form = form;
        $scope.getNeracaSaldo(form.tanggal)
    };
    /** save action */
    $scope.save = function (form) {
        
        
        var data = {
            form : form,
        }
        
        Data.post(control_link + '/save', data).then(function (result) {
            if (result.status_code == 200) {
                $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                $scope.cancel();
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
    
    $scope.delete = function (row) {
        var data = angular.copy(row);
        Swal.fire({
            title: "Peringatan ! ",
            text: "Apakah Anda Yakin Ingin Menghapus Data Ini",
            type: "warning",
            showCancelButton: true,
            confirmButtonColor: "#DD6B55",
            confirmButtonText: "Iya, di Hapus",
            cancelButtonText: "Tidak",
        }).then((result) => {
            if (result.value) {
                row.is_deleted = 1;
                Data.post(control_link + '/delete', row).then(function (result) {
                    Swal.fire({
                        title: "Terhapus",
                        text: "Data Berhasil Di Hapus Permanen.",
                        type: "success"
                    }).then(function () {
                        $scope.cancel();
                    });

                });
            }
        });

    };
});