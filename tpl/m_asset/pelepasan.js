app.controller('pelepasanCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/m_asset";
    var master = 'Pelepasan Asset';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;

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
        param['filter'] = {};
        if (tableState.search.predicateObject) {
            param['filter'] = tableState.search.predicateObject;
        }
        param['filter']['is_deleted'] = 0;
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
    });


    $scope.jurnal_create = function() {
    $scope.listAkutansi = [];
        var akun_masuk_nama = $scope.form.akun_masuk_id != undefined ? $scope.form.akun_masuk_id.nama : 'Pilih Akun Masuk';
        var akun_masuk_id = $scope.form.akun_masuk_id != undefined ? $scope.form.akun_masuk_id.id : 0;

        var akun_keluar_nama = $scope.form.akun_keluar_id != undefined ? $scope.form.akun_keluar_id.nama : 'Pilih Akun Keluar';
        var akun_keluar_id = $scope.form.akun_keluar_id != undefined ? $scope.form.akun_keluar_id.id : 0;
        var selisih = parseInt($scope.form.nilai_pelepasan != undefined ? $scope.form.nilai_pelepasan : 0) -  parseInt($scope.form.nilai_buku != undefined ? $scope.form.nilai_buku : 0);

        var m_akun_id = $scope.form.akun != undefined ? $scope.form.akun.id : null;
        var nm_akun = $scope.form.akun != undefined ? $scope.form.akun.nama : null;
        
        var nilai_buku = parseInt($scope.form.nilai_buku != undefined ? $scope.form.nilai_buku : 0);
        if (selisih > 0 ) {
            var deb = {
             'acc_asset_id': $scope.form.id,
             'm_akun_id': akun_masuk_id,
             'tanggal'  : $scope.form.tanggal_pelepasan,
             'no_transaksi':$scope.form.kode,
             'nama_akun':  akun_masuk_nama,
             'keterangan': 'Pelepasan Aset',
             'debit': $scope.form.nilai_pelepasan,
             'kredit': 0
            };
            console.log(deb)
            var kred1 = {
             'acc_asset_id': $scope.form.id,
             'm_akun_id': m_akun_id,
             'tanggal' : $scope.form.tanggal_pelepasan,
             'no_transaksi': $scope.form.kode,
             'nama_akun': "&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; " + nm_akun,
             'keterangan': 'Pelepasan Aset',
             'debit' : 0,
             'kredit' : nilai_buku
            };
             var kred2 = {
             'acc_asset_id': $scope.form.id,
             'm_akun_id': akun_keluar_id,
             'tanggal' : $scope.form.tanggal_pelepasan,
             'no_transaksi': $scope.form.kode,
             'nama_akun': "&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; " + akun_keluar_nama,
             'keterangan': 'Pelepasan Aset',
             'debit' : 0,
             'kredit' : selisih
            };
            $scope.listAkutansi.push(deb);
            $scope.listAkutansi.push(kred1);
            $scope.listAkutansi.push(kred2);
        } else{
            var deb1 = {
             'acc_asset_id': $scope.form.id,
             'm_akun_id': akun_masuk_id,
             'tanggal'  : $scope.form.tanggal_pelepasan,
             'no_transaksi':$scope.form.kode,
             'nama_akun':  akun_masuk_nama,
             'keterangan': 'Pelepasan Aset',
             'debit': $scope.form.nilai_pelepasan,
             'kredit': 0
            };
            var deb2 = {
             'acc_asset_id': $scope.form.id,
             'm_akun_id': akun_keluar_id,
             'tanggal' : $scope.form.tanggal_pelepasan,
             'no_transaksi': $scope.form.kode,
             'nama_akun':akun_keluar_nama,
             'keterangan': 'Pelepasan Aset',
             'debit' : selisih * (-1),
             'kredit' : 0
            };
            var kred1 = {
             'acc_asset_id': $scope.form.id,
             'm_akun_id': m_akun_id,
             'tanggal' : $scope.form.tanggal_pelepasan,
             'no_transaksi': $scope.form.kode,
             'nama_akun': "&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; " + nm_akun,
             'keterangan': 'Pelepasan Aset',
             'debit' : 0,
             'kredit' : nilai_buku
            };
            $scope.listAkutansi.push(deb1);
            $scope.listAkutansi.push(deb2);
            $scope.listAkutansi.push(kred1);
        }
        console.log($scope.listAkutansi);
        $scope.detAkutansi = $scope.listAkutansi;
        $scope.totalAkutansi = {};
       if (selisih > 0) {
        $scope.totalAkutansi.totalDebit = $scope.form.nilai_pelepasan;
        $scope.totalAkutansi.totalKredit = $scope.form.nilai_pelepasan;
       } else{
         $scope.totalAkutansi.totalDebit = nilai_buku;
         $scope.totalAkutansi.totalKredit = nilai_buku;
       }
      };

        
    /** update */
    $scope.update = function (form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_update = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | " + form.nama;
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal_beli);
        $scope.form.tanggal_pelepasan = new Date();
        $scope.form.harga = form.harga_beli;
        console.log(form);
    };
    /** view */
    $scope.view = function (form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Lihat Data : " + form.nama;
        $scope.form = form;
        $scope.form.tanggal_pelepasan = new Date(form.tanggal_pelepasan);
    };
    /** save action */
    $scope.save = function (form) {
        Data.post(control_link + "/save_pelepasan", form).then(function (result) {
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