app.controller('l_aruskasCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/l_arus_kas";
    var master = 'Laporan Arus Kas';
    $scope.master = master;
    $scope.formTitle = '';
    $scope.base_url = '';
    $scope.form = {};
    $scope.form.tanggal = {endDate: moment().add(1, 'M'), startDate: moment()};
    
    /*
     * ambil lokasi
     */
    Data.get('acc/m_lokasi/getLokasi').then(function (response) {
            $scope.listLokasi = response.data.list;
            if($scope.listLokasi.length > 0){
                $scope.form.m_lokasi_id = $scope.listLokasi[0];
            }
        });
    

    $scope.view = function (is_export, is_print) {
        $scope.mulai = moment($scope.form.tanggal.startDate).format('DD-MM-YYYY');
        $scope.selesai = moment($scope.form.tanggal.endDate).format('DD-MM-YYYY');
        var param = {
            export: is_export,
            print: is_print,
            m_lokasi_id: $scope.form.m_lokasi_id.id,
            nama_lokasi: $scope.form.m_lokasi_id.nama,
            startDate: moment($scope.form.tanggal.startDate).format('YYYY-MM-DD'),
            endDate: moment($scope.form.tanggal.endDate).format('YYYY-MM-DD'),
        };
        if (is_export == 0 && is_print == 0) {
            Data.get(control_link + '/laporan', param).then(function (response) {
                if (response.status_code == 200) {
                    $scope.data = response.data.data;
                    $scope.detail = response.data.detail;
                    $scope.tampilkan = true;
                } else {
                    $scope.tampilkan = false;
    //                toaster.pop('error', "Terjadi Kesalahan", setErrorMessage(response.errors));
                }
            });
        }else{
            window.open("api/acc/l_arus_kas/laporan?" + $.param(param), "_blank");
        }
        
    };

    $scope.exportData = function (clases) {
        var blob = new Blob([document.getElementById(clases).innerHTML], {
            type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;charset=utf-8"
        });
        saveAs(blob, "Laporan-Buku-Besar.xls");
    };
    
    $scope.exportExcel = function (form){
//        var param = {
//            
//        }
        form.tanggal.endDate = moment(form.tanggal.endDate).format('YYYY-MM-DD');
        form.tanggal.startDate = moment(form.tanggal.startDate).format('YYYY-MM-DD');
//        console.log(form)
        window.location = "api/acc/l_arus_kas/exportExcel?" + $.param(form);
    }

    $scope.resetAkun = function () {
        $scope.form.akun_id = undefined;
    }

//    $scope.resetSubUnit = function () {
//        $scope.form.m_unker = undefined;
//    }

    $scope.resetUnit = function () {
        $scope.form.unit = undefined;
        $scope.getCabang = $scope.allCabang;
    }

});