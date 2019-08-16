app.controller('saldoawalCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
//    var control_link = "m_supplier";
    var master = 'Transaksi Saldo Awal';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.form = {};
    $scope.form.tanggal = "";
    $scope.totalkredit = 0;
    $scope.totaldebit = 0;
    $scope.tutup = false;
//    $scope.form.m_fakultas_id = 1;

    Data.get('acc/m_akun/getTanggalSetting').then(function (data) {
        var tanggal = new Date(data.data.tanggal)
        tanggal.setDate(tanggal.getDate() - 1)
        $scope.form.tanggal = tanggal

    });

    Data.get('acc/m_lokasi/getLokasi').then(function (response) {
        $scope.listLokasi = response.data.list;
    });

    Data.get("acc/t_tutup_bulan/index", {filter: {jenis: "bulan"}}).then(function (response) {
        console.log(response)
        if (response.data.list.length > 0) {
            $scope.tutup = true;
        }
    });

    $scope.sumTotal = function () {
        var totaldebit = 0;
        var totalkredit = 0;
        console.log($scope.displayed)
        angular.forEach($scope.displayed, function (value, key) {
            totaldebit += parseInt(value.debit);
            totalkredit += parseInt(value.kredit);
        });
        $scope.totaldebit = totaldebit;
        $scope.totalkredit = totalkredit;
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
        $scope.isLoading = false;
    };

    $scope.view = function (form) {
        var param = {
            m_lokasi_id: form.m_lokasi_id.id,
            tanggal: form.tanggal
        };
        Data.get('acc/m_akun/getSaldoAwal', param).then(function (response) {
            $scope.displayed = response.data.detail;
            $scope.sumTotal();
            $scope.form.tanggal = new Date(response.data.tanggal);
            console.log(response)
//            $scope.form = {};
        });
    }
    /** save action */
    $scope.save = function (form) {
        console.log(form)
        console.log($scope.displayed)
        if ($scope.totaldebit == $scope.totalkredit) {
            var data = {
                form: form,
                detail: $scope.displayed
            }
            Data.post('acc/m_akun/saveSaldoAwal', data).then(function (result) {
                if (result.status_code == 200) {


                    Swal.fire({
                        title: "Tersimpan",
                        text: "Data Berhasil Di Simpan.",
                        type: "success"
                    }).then(function () {
                        $scope.callServer(tableStateRef);
                    });
                } else {
                    $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
                }
            });
        } else {
            $rootScope.alert("Terjadi Kesalahan", "Total debit dan kredit harus sama", "error");
        }

    };
    /** cancel action */
    $scope.cancel = function () {
        if (!$scope.is_view) {
            $scope.callServer(tableStateRef);
        }
        $scope.is_edit = false;
        $scope.is_view = false;
    };

    /*
     * export format
     */
    $scope.export = function () {
        window.location = 'api/acc/m_akun/exportSaldoAwal';
    };

    /**
     * import
     */
    $scope.uploadFiles = function (file, errFiles) {
        $scope.f = file;
        $scope.errFile = errFiles && errFiles[0];
        if (file) {
            Data.get('site/url').then(function (data) {
                file.upload = Upload.upload({
                    url: data.data + 'acc/m_akun/importSaldoAwal',
                    data: {
                        file: file
                    }
                });
                file.upload.then(function (response) {
                    var data = response.data.data;
                    if (response.data.status_code == 200) {
                        console.log(data)
                        $scope.displayed = data.detail;
                        $scope.sumTotal();
                        $scope.form.tanggal = new Date(data.data.tanggal);
                        $scope.form.m_lokasi_id = data.data.lokasi;
                    } else {
                        $rootScope.alert("Terjadi Kesalahan", setErrorMessage(response.errors), "error");
                    }
                });
            });
        } else {
            $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
        }
    };
});