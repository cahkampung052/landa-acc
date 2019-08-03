app.controller('PelepasanCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/m_asset";
    var master = 'Pelepasan Asset Tetap';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.options_min = {};
//    Data.get(control_link + '/cabang').then(function(data) {
//        $scope.cabang = data.data.data;
//    });

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
        Data.get(control_link + '/index', param).then(function (response) {
            $scope.displayed = response.data.list;
            $scope.base_url = response.data.base_url;
        });
        $scope.isLoading = false;
    };

    Data.get(control_link + '/getAkun').then(function (response) {
        $scope.listakun = response.data.list;
    });
    
    Data.get('acc/m_lokasi/index', {filter:{is_deleted:0}}).then(function (response) {
        $scope.listLokasi = response.data.list;
        // $scope.listLokasi.push({"id":-1,"nama":"Lainya" });

    });

    Data.get('acc/m_umur_ekonomis/index', {filter:{is_deleted:0}}).then(function (response) {
        $scope.listUmur = response.data.list;
    });
    $scope.cek_min_tgl = function(id){
        Data.get('acc/m_asset/get_min_tgl_pelepasan', {id:id}).then(function (response) {
            if (response.data.minimal==true) {
                 $scope.options_min = {
                      minDate: new Date(response.data.tanggal) 
                 };
                 $scope.form.tgl_pelepasan = new Date(response.data.tanggal);
            }else{
                $scope.options_min = {
                      minDate: '' 
                 };
                $scope.form.tgl_pelepasan = new Date();
            }

        });
    }
  
    /** detail_pelepasan */
    $scope.detail_pelepasan = function (form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_update = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Detail Pelepasan : " + form.nama;
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal_beli);
        $scope.form.harga = form.harga_beli;
        if (form.status == 'Aktif') {
            $scope.cek_min_tgl(form.id);
        }else{
            $scope.form.tgl_pelepasan = new Date(form.tgl_pelepasan);
        }
        $scope.form.jenis_pelepasan = 'Dijual';
    };
    /** view */
    $scope.view = function (form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Lihat Data : " + form.nama;
        $scope.form = form;
        $scope.form.jenis_pelepasan = form.status;
        if ($scope.form.jenis_pelepasan == 'Aktif') {
            $scope.form.tgl_pelepasan = new Date();
        }else{
            $scope.form.tgl_pelepasan = new Date(form.tgl_pelepasan);
        }
    };
    /** save action */
    $scope.proses_pelepasan = function (form) {
        Data.post(control_link + "/proses_pelepasan", form).then(function (result) {
            if (result.status_code == 200) {
                $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                $scope.callServer(tableStateRef);
                $scope.is_edit = false;
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
