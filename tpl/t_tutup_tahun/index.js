app.controller('tutuptahunCtrl', function($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/t_tutup_tahun";
    var master = 'Transaksi Tutup Tahun';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.tampilkan = false;
    Data.get('acc/m_akun/akunDetail').then(function(data) {
        $scope.listAkun = data.data.list;
    });
    $scope.getDetail = function(form) {
        var params = {
            akun_ikhtisar_id: form.akun_ikhtisar_id.id,
            akun_ikhtisar_nama: form.akun_ikhtisar_id.nama,
            akun_pemindahan_modal_id: form.akun_pemindahan_modal_id.id,
            akun_pemindahan_modal_nama: form.akun_pemindahan_modal_id.nama,
            tahun: form.tahun
        };
        if ((form.tahun != undefined) && (form.akun_ikhtisar_id != undefined) && (form.akun_pemindahan_modal_id != undefined)) {
            Data.get(control_link + '/getDetail', params).then(function(response) {
                $scope.detail = response.data.detail;
                $scope.data = response.data.data;
                $scope.jurnalPemindahan = response.data.jurnalPemindahan;
                $scope.jurnalPrive = response.data.jurnalPrive;
                $scope.tampilkan = true;
            });
        }
    };
    $scope.getView = function(id) {
        Data.get(control_link + '/getView', {id : id}).then(function(response) {
            $scope.detail = response.data.detail;
            $scope.data = response.data.data;
            $scope.jurnalPemindahan = response.data.jurnalPemindahan;
            $scope.jurnalPrive = response.data.jurnalPrive;
            $scope.tampilkan = true;
        });
    }
    $scope.sumTotal = function() {
        var totaldebit = 0;
        angular.forEach($scope.listDetail, function(value, key) {
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
        var param = {
            offset: offset,
            limit: limit
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
        Data.get(control_link + '/index', param).then(function(response) {
            $scope.displayed = response.data.list;
            $scope.base_url = response.data.base_url;
            tableState.pagination.numberOfPages = Math.ceil(response.data.totalItems / limit);
        });
        $scope.isLoading = false;
    };
    /** create */
    $scope.create = function() {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.is_disable = false;
        $scope.formtitle = master + " | Form Tambah Data";
        $scope.form = {};
        $scope.listDetail = [{}];
        $scope.tampilkan = false;
    };
    /** update */
    $scope.update = function(form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_update = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Edit Data : ";
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal);
        $scope.getView(form.id);
    };
    /** view */
    $scope.view = function(form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Lihat Data : " + form.tahun;
        $scope.form = form;
        $scope.getView(form.id);
    };
    /** save action */
    $scope.save = function(form) {
        var data = {
            form : form,
            tahun : moment(form.tahun).format('YYYY'),
            detail : $scope.detail,
            jurnalPemindahan : $scope.jurnalPemindahan,
            jurnalPrive : $scope.jurnalPrive
        }
        Data.post(control_link + '/save', data).then(function(result) {
            if (result.status_code == 200) {
                Swal.fire({
                    title: "Tersimpan",
                    text: "Data Berhasil Di Simpan.",
                    type: "success"
                }).then(function() {
                    $scope.callServer(tableStateRef);
                    $scope.is_edit = false;
                });
            } else {
                Swal.fire("Gagal", result.errors, "error");
            }
        });
    };
    /** cancel action */
    $scope.cancel = function() {
        if (!$scope.is_view) {
            $scope.callServer(tableStateRef);
        }
        $scope.is_edit = false;
        $scope.is_view = false;
    };
    $scope.delete = function(row) {
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
});