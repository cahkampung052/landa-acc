app.controller('l_neracaCtrl', function($scope, Data, $rootScope, $uibModal) {
    var control_link = "acc/l_neraca";
    $scope.form = {};
    $scope.url = {};
    $scope.form.tanggal = new Date();
    $scope.form.is_detail = 1;
    
    Data.get('site/base_url').then(function (response) {
        $scope.url = response.data;
    });
    
     /**
     * Ambil laporan dari server
     */
    $scope.view = function(is_export, is_print) {
        $scope.tanggal = moment($scope.form.tanggal).format('DD-MM-YYYY');
        var param = {
            export: is_export,
            print: is_print,
            tanggal: moment($scope.form.tanggal).format('YYYY-MM-DD'),
            is_detail : $scope.form.is_detail
//            m_lokasi_id : $scope.form.m_lokasi_id.id
        };
        if (is_export == 0 && is_print == 0) {
            Data.get(control_link + '/laporan', param).then(function(response) {
                if (response.status_code == 200) {
                    $scope.data = response.data;
                    $scope.detail = response.data.detail;
                    $scope.tampilkan = true;
                } else {
                    $rootScope.alert("Terjadi Kesalahan", setErrorMessage(response.errors), "error");
                    $scope.tampilkan = false;
                }
            });
        } else {
            Data.get('site/base_url').then(function(response){
                window.open(response.data.base_url + "api/acc/l_neraca/laporan?" + $.param(param), "_blank");
            });
            
        }
    };
    
    /**
     * Modal setting pengecualian
     */
    $scope.modalSetting = function() {
        var modalInstance = $uibModal.open({
            templateUrl: $scope.url.base_url + "api/" + $scope.url.acc_dir + "/tpl/l_neraca/modal.html",
            controller: "settingNeracaCtrl",
            size: "md",
            backdrop: "static",
            keyboard: false,
        });
        modalInstance.result.then(function(response) {
            if (response.data == undefined) {} else {}
        });
    }
    
});

app.controller("settingNeracaCtrl", function($state, $scope, Data, $uibModalInstance, $rootScope) {
    
    $scope.listAkun = [];
    
    Data.get('acc/m_akun/getPengecualian').then(function(response){
        $scope.listAkun = response.data.pengecualian_neraca;
    });
    
    Data.get('acc/m_akun/akunDetail').then(function(data) {
        $scope.akunDetail = data.data.list;
    });
    /**
     * Tambah detail
     */
    $scope.addDetail = function(val) {
        var comArr = $(".tabletr").last().index() + 1
        var newDet = {
            m_akun_id: {
                id: $scope.akunDetail[0].id,
                kode: $scope.akunDetail[0].kode,
                nama: $scope.akunDetail[0].nama
            },
        };
        console.log(val)
        if(val != null){
            val.splice(comArr, 0, newDet);
        }else{
            $scope.listAkun = [];
            $scope.listAkun[0] = {
                m_akun_id: {
                    id: $scope.akunDetail[0].id,
                    kode: $scope.akunDetail[0].kode,
                    nama: $scope.akunDetail[0].nama
                },
            }
        }
        
    };
    /**
     * Hapus detail
     */
    $scope.removeDetail = function(val, paramindex) {
        var comArr = eval(val);
        val.splice(paramindex, 1);
    };
    
    $scope.save = function() {
            
            var params = {
                type : "neraca",
                data : $scope.listAkun
            }
            
            Data.post('acc/m_akun/savePengecualian', params).then(function(result) {
                if (result.status_code == 200) {
                    $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                    $uibModalInstance.close({
                        'data': result.data
                    });
                } else {
                    $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
                }
            });
    };
    $scope.close = function() {
        $uibModalInstance.close({
            'data': undefined
        });
    };
});