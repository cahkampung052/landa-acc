app.controller('penerimaanCtrl', function($scope, Data, $rootScope, $uibModal, Upload, FileUploader) {
    var tableStateRef;
    var control_link = "acc/t_penerimaan";
    var master = 'Transaksi Penerimaan';
    $scope.master = master;
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.gambar = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.urlfoto = "api/file/penerimaan/";
    
    Data.get('acc/m_akun/akunKas').then(function(data) {
        $scope.akun = data.data.list;
    });
    Data.get('acc/m_akun/akunDetail').then(function(data) {
        $scope.akunDetail = data.data.list;
    });
    Data.get('acc/m_customer/getKontak').then(function(response) {
        $scope.listCustomer = response.data.list;
    });
    Data.get('acc/m_lokasi/getLokasi').then(function(response) {
        $scope.listLokasi = response.data.list;
    });
    Data.get('acc/m_akun/getTanggalSetting').then(function(response) {
        
        $scope.tanggal_setting = response.data.tanggal;
        
        $scope.options = {
            minDate: new Date(response.data.tanggal),
        };
    });
    /**
     * Upload Gambar
     */
    var uploader = $scope.uploader = new FileUploader({
        url: Data.base + 'acc/t_penerimaan/upload/bukti',
        formData: [],
        removeAfterUpload: true,
    });
    $scope.uploadGambar = function() {
        $scope.uploader.uploadAll();
    };
    uploader.filters.push({
        name: 'imageFilter',
        fn: function(item) {
            var type = '|' + item.type.slice(item.type.lastIndexOf('/') + 1) + '|';
            var x = '|jpg|png|jpeg|bmp|gif|'.indexOf(type) !== -1;
            if (!x) {
                $rootScope.alert("Terjadi Kesalahan", "Jenis gambar tidak sesuai", "error");
            }
            return x;
        }
    });
    uploader.filters.push({
        name: 'sizeFilter',
        fn: function(item) {
            var xz = item.size < 2097152;
            if (!xz) {
                $rootScope.alert("Terjadi Kesalahan", "Ukuran gambar tidak boleh lebih dari 2MB", "error");
            }
            return xz;
        }
    });
//    uploader.onSuccessItem = function(fileItem, response) {
//        if (response.answer == 'File transfer completed') {
//            var d = new Date();
//            $scope.gambar.unshift({
//                img: response.img,
//                id: response.id
//            });
//            $scope.urlgambar = "api/file/penerimaan/" + d.getFullYear() + "/" + (d.getMonth() + 1) + "/";
//        }
//    };
    uploader.onBeforeUploadItem = function(item) {
        item.formData.push({
            id: $scope.form.id,
        });
    };
    $scope.removeFoto = function(paramindex, namaFoto, pid) {
        Data.post('acc/t_penerimaan/removegambar', {
            id: pid,
            img: namaFoto
        }).then(function(data) {
            $scope.gambar.splice(paramindex, 1);
        });
    };
    $scope.gambarzoom = function(img) {
        var modalInstance = $uibModal.open({
            template: '<center><img src="' + $scope.urlfoto + img + '" class="img-fluid" ></center>',
            size: 'md',
        });
    };
    $scope.listgambar = function(id) {
        Data.get('acc/t_penerimaan/listgambar/' + id).then(function(data) {
            $scope.gambar = data.data.model;
        });
    };
    /**
     * Ambil detail
     */
    $scope.getDetail = function(id) {
        var data = {
            id: id
        }
        Data.get(control_link + '/getDetail', data).then(function(data) {
            $scope.listDetail = data.data.list;
        });
    }
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
            m_lokasi_id: {
                id: $scope.listLokasi[0].id,
                nama: $scope.listLokasi[0].nama,
                kode: $scope.listLokasi[0].kode
            },
            keterangan: '',
            kredit: 0,
            is_label: false,
        };
        $scope.sumTotal();
        val.splice(comArr, 0, newDet);
    };
    /**
     * Hapus detail
     */
    $scope.removeDetail = function(val, paramindex) {
        var comArr = eval(val);
        if (comArr.length > 1) {
            val.splice(paramindex, 1);
            $scope.sumTotal();
        } else {
            alert("Something gone wrong");
        }
    };
    /**
     * Kalkulasi total detail
     */
    $scope.sumTotal = function() {
        var totalkredit = 0;
        angular.forEach($scope.listDetail, function(value, key) {
            totalkredit += parseInt(value.kredit);
        });
        $scope.form.total = totalkredit;
    };
    
    /*
     * empty ui-select
     */
    $scope.emptyUi = function (ui) {
        console.log(ui)
        $scope.form.m_kontak_id = [];
    }
    
    $scope.callServer = function callServer(tableState) {
        tableStateRef = tableState;
        $scope.isLoading = true;
        var offset = tableState.pagination.start || 0;
        var limit = tableState.pagination.number || 1000;
        /** 
         * set offset and limit
         */
        var param = {};
        /** 
         * set sort and order
         */
        if (tableState.sort.predicate) {
            param['sort'] = tableState.sort.predicate;
            param['order'] = tableState.sort.reverse;
        }
        /** 
         * set filter
         */
        if (tableState.search.predicateObject) {
            param['filter'] = tableState.search.predicateObject;
        }
        Data.get('acc/m_lokasi/getLokasi', param).then(function(response) {
            $scope.listLokasi = response.data.list;
        });
        Data.get(control_link + '/index', param).then(function(response) {
            $scope.displayed = response.data.list;
            $scope.base_url = response.data.base_url;
        });
        $scope.isLoading = false;
    };
    /** 
     * create
     */
    $scope.create = function() {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.is_disable = false;
        $scope.formtitle = master + " | Form Tambah Data";
        $scope.form = {};
        $scope.form.tanggal = new Date($scope.tanggal_setting);
        if(new Date() >= new Date($scope.tanggal_setting)){
            $scope.form.tanggal = new Date();
        }
        $scope.listDetail = [{
            m_akun_id: {
                id: $scope.akunDetail[0].id,
                kode: $scope.akunDetail[0].kode,
                nama: $scope.akunDetail[0].nama
            },
            m_lokasi_id: {
                id: $scope.listLokasi[0].id,
                nama: $scope.listLokasi[0].nama,
                kode: $scope.listLokasi[0].kode
            },
            kredit: 0
        }];
        $scope.sumTotal();
        $scope.gambar = [];
        $scope.urlfoto = "";
    };
    /** 
     * update
     */
    $scope.update = function(form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_update = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Edit Data : " + form.no_transaksi;
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal);
        $scope.tanggal_foto = new Date(form.tanggal);
        $scope.getDetail(form.id);
        $scope.listgambar(form.id);
        $scope.urlfoto += $scope.tanggal_foto.getFullYear() +"/"+ (parseInt($scope.tanggal_foto.getMonth())+1) +"/";
        
    };
    /** 
     * view
     */
    $scope.view = function(form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Lihat Data : " + form.no_transaksi;
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal);
        $scope.tanggal_foto = new Date(form.tanggal);
        $scope.getDetail(form.id);
        $scope.listgambar(form.id);
        console.log(form)
        $scope.urlfoto += $scope.tanggal_foto.getFullYear() +"/"+ (parseInt($scope.tanggal_foto.getMonth())+1) +"/";
    };
    /**
     * save action
     */
    $scope.save = function(form, type_save) {
        form["status"] = type_save;
        var data = {
            form: form,
            detail: $scope.listDetail,
        }
        Data.post(control_link + '/save', data).then(function(result) {
            if (result.status_code == 200) {
                $scope.form.id = result.data.id;
                $scope.uploadGambar();
                $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                $scope.cancel();
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
            }
        });
    };
    /** 
     * cancel action
     */
    $scope.cancel = function() {
        if (!$scope.is_view) {
            $scope.callServer(tableStateRef);
        }
        $scope.is_edit = false;
        $scope.is_view = false;
        $scope.urlfoto = "api/file/penerimaan/";
    };
    /**
     * Hapus transaksi
     */
    $scope.delete = function(row) {
        var data = angular.copy(row);
        Swal.fire({
            title: "Peringatan ! ",
            text: "Apakah Anda Yakin Ingin Menghapus Permanen Data Ini",
            type: "warning",
            showCancelButton: true,
            confirmButtonColor: "#DD6B55",
            confirmButtonText: "Iya, di Hapus",
            cancelButtonText: "Tidak",
        }).then((result) => {
            if (result.value) {
                row.is_deleted = 1;
                Data.post(control_link + '/delete', row).then(function(result) {
                    Swal.fire({
                        title: "Terhapus",
                        text: "Data Berhasil Di Hapus Permanen.",
                        type: "success"
                    }).then(function() {
                        $scope.cancel();
                    });
                });
            }
        });
    };
    
    $scope.print = function (row) {
        var data = angular.copy(row);
        window.open("api/acc/t_penerimaan/print?" + $.param(row), "_blank");
    };
});