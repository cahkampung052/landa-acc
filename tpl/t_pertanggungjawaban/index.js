app.controller("tpertanggungjawabanCtrl", function ($scope, Data,$rootScope) {
    /**
     * Inialisasi
     */
    var tableStateRef;
    $scope.formtittle = "";
    $scope.displayed = [];
    $scope.form = {};
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.is_create = false;
    $scope.is_copy = false;
    $scope.loading = false;
    var master = "Transaksi Pembayaran";
    $scope.master = master;
    /**
     * Ambil semua lokasi
     */
    Data.get('acc/m_lokasi/getLokasi').then(function (response) {
        $scope.listLokasi = response.data.list;
        if ($scope.listLokasi.length > 0) {
            $scope.form.m_lokasi_id = $scope.listLokasi[0];
        }
    });
    
    /*
     * ambil user
     */
    Data.get("acc/appuser/getAll").then(function (response) {
        $scope.listUser = response.data;
    });
    
    /*
     * ambil akun
     */
    Data.get("acc/m_akun/akunDetail").then(function (response){
        $scope.listAkun = response.data.list;
        
    });

    /**
     * End inialisasi
     */
    $scope.callServer = function callServer(tableState) {
        tableStateRef = tableState;
        $scope.isLoading = true;
        var offset = tableState.pagination.start || 0;
        var limit = tableState.pagination.number || 10;
        var param = {
            offset: offset,
            limit: limit
        };
        if (tableState.sort.predicate) {
            param["sort"] = tableState.sort.predicate;
            param["order"] = tableState.sort.reverse;
        }
        if (tableState.search.predicateObject) {
            param["filter"] = tableState.search.predicateObject;
        }
        Data.get("acc/apppertanggungjawaban/index", param).then(function (response) {
            $scope.displayed = response.data.list;
            tableState.pagination.numberOfPages = Math.ceil(
                    response.data.totalItems / limit
                    );
        });
        $scope.isLoading = false;
    };
    $scope.getDetail = function (id) {
        Data.get("apppertanggungjawaban/view?t_pengajuan_id=" + id).then(function (response) {
            $scope.listDetail = response.data;
                angular.forEach($scope.listDetail, function(value, key){
                    value.acc_m_akun_id = $scope.listAkun[0];
                 });
            
        });
    };
    $scope.listDetail = [{}];
    $scope.addDetail = function (val) {
        var comArr = eval(val);
        var newDet = {};
        val.push(newDet);
    };
    $scope.removeDetail = function (val, paramindex) {
        var comArr = eval(val);
        if (comArr.length > 1) {
            val.splice(paramindex, 1);
        } else {
            alert("Something gone wrong");
        }
    };
    $scope.sumTotal = function() {
        var jumlah_perkiraan = 0;
        angular.forEach($scope.listDetail, function(value, key) {
            if(value.harga_satuan === undefined){
                value.harga_satuan = 0;
            }
            if(value.jenis_satuan === undefined){
                value.jenis_satuan = 0;
            }
            value.sub_total = parseInt(value.harga_satuan) * parseInt(value.jenis_satuan);
            jumlah_perkiraan += value.sub_total;
        });
        $scope.form.jumlah_perkiraan = jumlah_perkiraan;
    };
    $scope.getAcc = function (id) {
        Data.get("apppertanggungjawaban/getAcc?t_pengajuan_id=" + id).then(function (response) {
            $scope.listAcc = response.data;
        });
    };
    $scope.listAcc = [{}];
    $scope.addAcc = function (val) {
        var comArr = eval(val);
        var newDet = {};
        val.push(newDet);
    };
    $scope.removeDetail = function (val, paramindex) {
        var comArr = eval(val);
        if (comArr.length > 1) {
            val.splice(paramindex, 1);
        } else {
            alert("Something gone wrong");
        }
    };
    $scope.create = function (form) {
        $scope.is_copy = false;
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.formtittle = master + " | Form Tambah Data";
        $scope.form = {};
        $scope.form.tanggal = new Date();
        $scope.form.approval = 1;
        $scope.listDetail = [{}];
        $scope.listAcc = {};
    };
    
    $scope.copy = function (form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.is_copy = true;
        $scope.formtittle = master + " | Form Salin Data";
        $scope.form = {};
        $scope.form.tanggal = new Date();
        $scope.form.approval = 1;
        $scope.listDetail = [{}];
        $scope.listAcc = {};
        /*
        * ambil pengajuan untuk copy
        */
       Data.get("apppertanggungjawaban/getAll").then(function (response) {
           $scope.listPengajuan = response.data;
           console.log(response.data)
       });
    };
    
    $scope.update = function (form) {
        $scope.is_copy = false;
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = false;
        $scope.formtittle = master + " | Edit Data : " + form.no_proposal;
        $scope.form = form;
        $scope.getDetail(form.id);
        $scope.getAcc(form.id);
        $scope.form.tanggal = new Date(form.tanggal);
    };
    $scope.view = function (form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.formtittle = master + " | Lihat Data : " + form.no_proposal;
        $scope.form = form;
        $scope.getDetail(form.id);
        $scope.getAcc(form.id);
        $scope.form.tanggal = new Date(form.tanggal);
    };
    $scope.save = function (form) {
        $scope.loading = true;
        var form = {
            data: form,
            detail: $scope.listDetail,
            acc: $scope.listAcc
        }
        Data.post("apppertanggungjawaban/save", form).then(function (result) {
            if (result.status_code == 200) {
                $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                $scope.cancel();
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
            }
            $scope.loading = false;
        });
    };
    $scope.cancel = function () {
        $scope.is_edit = false;
        $scope.is_view = false;
        $scope.is_create = false;
        $scope.callServer(tableStateRef);
    };
    $scope.delete = function (row) {
        if (confirm("Apa anda yakin akan Menghapus item ini ?")) {
            row.is_deleted = 0;
            Data.post("apppertanggungjawaban/hapus", row).then(function (result) {
                $scope.displayed.splice($scope.displayed.indexOf(row), 1);
            });
        }
    };
    
    $scope.getPengajuan = function (no_proposal) {
        Data.get("apppertanggungjawaban/getAll?id="+no_proposal.id).then(function (response) {
            $scope.form = response.data[0];
            $scope.form.tanggal = new Date($scope.form.tanggal)
            $scope.getDetail($scope.form.id)
            console.log($scope.listDetail)
            
            $scope.form.no_proposal = "";
            $scope.form.id = "";
            $scope.tersalin_dari = no_proposal;
        });
    }
});
