app.controller('penyusutanassetCtrl', function ($scope, Data, $rootScope,$uibModal) {
    var tableStateRef;
    var control_link = "acc/m_asset";
    var master = 'Penyusutan Asset Tetap';
    $scope.formTitle = '';
    $scope.listDetail = [];
    $scope.base_url = '';
    $scope.is_show = false;
    $scope.filter = {};
    $scope.cari = {};
    $scope.filter.bulan = new Date();
    $scope.is_riwayat = false;
    $scope.displayed = [];
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
        // if (tableState.search.predicateObject) {
        //     param['filter'] = tableState.search.predicateObject;
        // }
        param['filter'] = {};
        if ($scope.cari.lokasi!=undefined) {
            param['filter']['lokasi'] = $scope.cari.lokasi.id;    
        }
        if ($scope.cari.bulan!=undefined) {
            param['filter']['bulan'] = moment($scope.cari.bulan).format('YYYY-MM-DD');
        }
        
        Data.get(control_link + '/list_penyusutan', param).then(function (response) {
            $scope.displayed = response.data.list;
            $scope.base_url = response.data.base_url;
        });
        $scope.isLoading = false;
    };

    $scope.cari_riwayat = function(cari){
        $scope.callServer(tableStateRef);
    }

    
    Data.get('acc/m_lokasi/index', {filter:{is_deleted:0}}).then(function (response) {
        $scope.listLokasi = response.data.list;
    });

    $scope.show_riwayat = function(param){
        if (param==true) {
            // $scope.callServer(tableStateRef);
        }
        $scope.is_riwayat = param;
    }

    $scope.trash = function (row) {
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
                Data.post(control_link + '/hapus_penyusutan', row).then(function (result) {
                    $scope.callServer(tableStateRef);
                    $rootScope.alert("Berhasil", "Data berhasil dihapus", "success");
                });
            }
        });
    };

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
                $scope.callServer(tableStateRef);
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors) ,"error");
            }
        });
    }

    $scope.reset = function(){
        $scope.listDetail = [];
        $scope.filter = {};
        $scope.filter.bulan = new Date();
        $scope.is_show = false;
    }

    $scope.detailRiw = function (form) {
        var modalInstance = $uibModal.open({
            templateUrl: $rootScope.pathModulAcc + "tpl/m_asset/modal_riwayat_penyusutan.html",
            controller: "riwPenyusutanCtrl",
            size: "lg",
            backdrop: "static",
            keyboard: false,
            resolve: {
                form: form,
            }
        });

        modalInstance.result.then(function (response) {
            if (response.data == undefined) {
            } else {
            }
        });
    }
});


app.controller("riwPenyusutanCtrl", function ($state, $scope, Data, $uibModalInstance, form) {
    $scope.form = form;
    $scope.listDetail = [];

    $scope.detail_riw_penyusutan = function (id) {

        Data.get('acc/m_asset/detail_riw_penyusutan', {id: id}).then(function (result) {
            $scope.listDetail = result.data.list;
        });
    };

    $scope.detail_riw_penyusutan(form.id);


    $scope.close = function () {
        $uibModalInstance.close({'data': undefined});
    };

});