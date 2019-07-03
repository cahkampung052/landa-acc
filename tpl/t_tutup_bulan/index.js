app.controller('tutupbulanCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/t_tutup_bulan";
    var master = 'Transaksi Tutup Bulan';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;

    Data.get('acc/m_akun/akunDetail').then(function(data) {
        $scope.listAkun = data.data.list;
    });
    
    Data.get('acc/t_tutup_bulan/tahun').then(function (response) {
            $scope.listTahun = response.data;
        });

    
    $scope.getDetail = function (){
        console.log("ya")
        var data = $scope.form;
        if ((data.bulan != undefined) && (data.tahun != undefined) && (data.akun_ikhtisar_id != undefined) && (data.akun_pemindahan_modal_id != undefined)) {
            Data.get('acc/t_tutup_bulan/getDetail', data).then(function (response) {
                $scope.listDetail  = response.data.list;
                $scope.total_debit = response.data.total_debit;
                $scope.total_kredit = response.data.total_kredit;
                $scope.nama_debit = data.akun_pemindahan_modal_id.nama;
                $scope.nama_kredit = data.akun_ikhtisar_id.nama;
            });
        }

    }
    
    
    $scope.sumTotal = function () {
        console.log("ya")
        var totaldebit = 0;
        angular.forEach($scope.listDetail, function (value, key) {
            totaldebit += parseInt(value.debit);
        });
        console.log(totaldebit)
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
        
        form['hasil_lr'] = parseInt($scope.total_debit)-parseInt($scope.total_kredit);
        console.log(form)
        console.log($scope.listDetail)
        
        var data = {
            form : form,
            detail : $scope.listDetail,
            total_debit : $scope.total_debit,
            total_kredit : $scope.total_kredit
        }
        
        Data.post(control_link + '/create', data).then(function (result) {
            if (result.status_code == 200) {


                Swal.fire({
                    title: "Tersimpan",
                    text: "Data Berhasil Di Simpan.",
                    type: "success"
                }).then(function () {
                    $scope.callServer(tableStateRef);
                    $scope.is_edit = false;
                });
            } else {
                Swal.fire({
                    title: "Gagal",
                    text: result.errores,
                    type: "error"
                }).then(function () {
                    $scope.callServer(tableStateRef);
                    $scope.is_edit = false;
                });
                
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