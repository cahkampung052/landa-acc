app.controller('jurnalCtrl', function ($scope, Data, $rootScope, $uibModal, Upload, FileUploader) {
    var tableStateRef;
    var control_link = "acc/t_jurnal_umum";
    var master = 'Transaksi Jurnal Umum';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;

    Data.get('acc/m_akun/akunKas').then(function(data) {
        $scope.akunKas = data.data.list;
    });
    Data.get('acc/m_akun/akunAll').then(function(data) {
        $scope.akunAll = data.data.list;
    });
    
    //============================GAMBAR===========================//
    var uploader = $scope.uploader = new FileUploader({
        url: Data.base + 'acc/t_jurnal_umum/upload/bukti',
        formData: [],
        removeAfterUpload: true,
    });

    $scope.uploadGambar = function (form) {
        $scope.uploader.uploadAll();
    };

    uploader.filters.push({
        name: 'imageFilter',
        fn: function (item) {
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
        fn: function (item) {
            var xz = item.size < 2097152;
            if (!xz) {
                $rootScope.alert("Terjadi Kesalahan", "Ukuran gambar tidak boleh lebih dari 2MB", "error");
            }
            return xz;
        }
    });

    $scope.gambar = [];

    uploader.onSuccessItem = function (fileItem, response) {
        if (response.answer == 'File transfer completed') {
            $scope.gambar.unshift({img: response.img, id: response.id});
        }
    };

    uploader.onBeforeUploadItem = function (item) {
        item.formData.push({
            id: $scope.form.id,
        });
    };

    $scope.removeFoto = function (paramindex, namaFoto, pid) {
        Data.post('acc/t_jurnal_umum/removegambar', {id: pid, img: namaFoto}).then(function (data) {
            $scope.gambar.splice(paramindex, 1);
        });

    };
    $scope.gambarzoom = function (img) {
        var modalInstance = $uibModal.open({
            template: '<center><img src="api/acc/landa-acc/upload/bukti/' + img + '" class="img-responsive" ></center>',
            size: 'md',
        });
    };

    $scope.listgambar = function (id) {
        Data.get('acc/t_jurnal_umum/listgambar/' + id).then(function (data) {
            $scope.gambar = data.data;
        });
    };
    /* sampe di sini*/

    
    $scope.getDetail = function (id){
        console.log(id)
        var data = {
            id : id
        }
        Data.get(control_link + '/getDetail', data).then(function(data) {
            $scope.listDetail = data.data.list;
        });
    }
    
    
    $scope.addDetail = function (val) {
        var comArr = $(".tabletr").last().index() + 1
        var newDet = {
            m_akun_id: '',
            keterangan : '',
            kredit : 0,
            debit : 0,
            is_label: false,
        };
        val.splice(comArr, 0, newDet);
        $scope.sumTotal();
    };
    $scope.removeDetail = function (val, paramindex) {
        console.log(val.paramindex)
        var comArr = eval(val);
        if (comArr.length > 1) {
            val.splice(paramindex, 1);
            $scope.sumTotal();
        } else {
            alert("Something gone wrong");
        }
    };
    
    $scope.sumTotal = function () {
        console.log("ya")
        var totalkredit = 0;
        var totaldebit = 0;
        angular.forEach($scope.listDetail, function (value, key) {
            totalkredit += parseInt(value.kredit);
            totaldebit += parseInt(value.debit);
        });
        $scope.form.total_debit = totaldebit;
        $scope.form.total_kredit = totalkredit;
    };
    
    $scope.kode = function (lokasi) {
        Data.get(control_link + '/kode/' + lokasi.kode).then(function (response) {
            $scope.form.no_transaksi = response.data.kode;
            $scope.form.no_urut = response.data.urutan;
        });
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
        
        Data.get(control_link + '/index', param).then(function (response) {
            $scope.displayed = response.data.list;
            $scope.base_url = response.data.base_url;
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
        $scope.form.tanggal = new Date();
        $scope.listDetail = [{}];
    };
    /** update */
    $scope.update = function (form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_update = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Edit Data : " + form.no_transaksi;
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal);
        $scope.getDetail(form.id);
        $scope.listgambar(form.id);
        console.log($scope.form);
        
    };
    /** view */
    $scope.view = function (form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Lihat Data : " + form.nama;
        $scope.form = form;
    };
    /** save action */
    $scope.save = function (form) {
        var data = {
            form : form,
            detail : $scope.listDetail
        }
        
        console.log(data)
        var url = (form.id > 0) ? '/update' : '/create';
        Data.post(control_link + url, data).then(function (result) {
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
                row.is_deleted = 1;
                Data.post(control_link + '/trash', row).then(function (result) {
                    $rootScope.alert("Berhasil", "Data berhasil dihapus", "success");
                $scope.cancel();

                });
            }
        });
    };
    $scope.restore = function (row) {
        var data = angular.copy(row);
        Swal.fire({
            title: "Peringatan ! ",
            text: "Apakah Anda Yakin Ingin Merestore Data Ini",
            type: "warning",
            showCancelButton: true,
            confirmButtonColor: "#DD6B55",
            confirmButtonText: "Iya, di Restore",
            cancelButtonText: "Tidak",
        }).then((result) => {
            if (result.value) {
                row.is_deleted = 0;
                Data.post(control_link + '/trash', row).then(function (result) {
                    $rootScope.alert("Berhasil", "Data berhasil direstore", "success");
                $scope.cancel();

                });
            }
        });
    };
    $scope.delete = function (row) {
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
                Data.post(control_link + '/delete', row).then(function (result) {
                    $rootScope.alert("Berhasil", "Data berhasil dihapus permanen", "success");
                $scope.cancel();

                });
            }
        });

    };
});