app.controller('pengeluaranCtrl', function ($scope, Data, $rootScope, $uibModal, Upload, FileUploader) {
    var tableStateRef;
    var control_link = "acc/t_pengeluaran";
    var master = 'Transaksi Pengeluaran';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;

    Data.get('acc/m_akun/akunKas').then(function(data) {
        $scope.akun = data.data.list;
    });
    
    Data.get('acc/m_akun/akunDetail').then(function(data) {
        $scope.akunDetail = data.data.list;
    });
    
    Data.get('acc/m_supplier/getSupplier').then(function (response) {
        $scope.listSupplier = response.data.list;
    });
    
    Data.get('acc/m_lokasi/getLokasi').then(function (response) {
        $scope.listLokasi = response.data.list;
    });
    
    
    //============================GAMBAR===========================//
    var uploader = $scope.uploader = new FileUploader({
        url: Data.base + 'acc/t_pengeluaran/upload/bukti',
        formData: [],
        removeAfterUpload: true,
    });

    $scope.uploadGambar = function () {
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
            var d = new Date();
            $scope.gambar.unshift({img: response.img, id: response.id});
            $scope.urlgambar = "api/file/pengeluaran/"+d.getFullYear()+"/"+(d.getMonth()+1)+"/";
        }
    };

    uploader.onBeforeUploadItem = function (item) {
        item.formData.push({
            id: $scope.form.id,
        });
    };

    $scope.removeFoto = function (paramindex, namaFoto, pid) {
        Data.post('acc/t_pengeluaran/removegambar', {id: pid, img: namaFoto}).then(function (data) {
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
        console.log(id)
        Data.get('acc/t_pengeluaran/listgambar/' + id).then(function (data) {
            $scope.gambar = data.data.model;
            $scope.url = data.data.url;
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
        console.log($scope.akunDetail)
        var newDet = {
            m_akun_id: {
                id : $scope.akunDetail[0].id,
                kode : $scope.akunDetail[0].kode,
                nama : $scope.akunDetail[0].nama
            },
            m_lokasi_id: {
                id : $scope.listLokasi[0].id,
                nama : $scope.listLokasi[0].nama
            },
            keterangan : '',
            debit : 0,
            is_label: false,
        };
        $scope.sumTotal();
        val.splice(comArr, 0, newDet);
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
        var totaldebit = 0;
        angular.forEach($scope.listDetail, function (value, key) {
            totaldebit += parseInt(value.debit);
        });
        $scope.form.total = totaldebit;
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
        $scope.listDetail = [{
            m_akun_id: {
                id : $scope.akunDetail[0].id,
                kode : $scope.akunDetail[0].kode,
                nama : $scope.akunDetail[0].nama
            },
            m_lokasi_id: {
                id : $scope.listLokasi[0].id,
                nama : $scope.listLokasi[0].nama
            },
            debit : 0
        }];
        $scope.sumTotal();
        $scope.gambar = {};
        $scope.url = "";
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
//        $scope.sumTotal();
        $scope.listgambar(form.id);
        console.log($scope.form);
        
    };
    /** view */
    $scope.view = function (form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Lihat Data : " + form.no_transaksi;
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal);
        $scope.getDetail(form.id);
        $scope.listgambar(form.id);
    };
    /** save action */
    $scope.save = function (form) {
        var data = {
            form : form,
            detail : $scope.listDetail
        }
        
        console.log(data)
//        var url = (form.id > 0) ? '/update' : '/create';
        Data.post(control_link + '/save', data).then(function (result) {
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
                    Swal.fire({
                        title: "Terhapus",
                        text: "Data Berhasil Di Hapus.",
                        type: "success"
                    }).then(function () {
                        $scope.cancel();
                    });

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
                    Swal.fire({
                        title: "Restore",
                        text: "Data Berhasil Di Restore.",
                        type: "success"
                    }).then(function () {
                        $scope.cancel();
                    });

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