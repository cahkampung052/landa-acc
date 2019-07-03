app.controller('l_jurnalumumCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/l_jurnal_umum";
    var master = 'Laporan Jurnal Umum';
    $scope.master = master;
    $scope.formTitle = '';
    $scope.base_url = '';
    $scope.form = {};

//    Data.get(control_link + '/cabang').then(function(data) {
//        $scope.cabang = data.data.data;
//    });

    $scope.form = {};
    $scope.form.tanggal = {endDate: moment().add(1, 'M'), startDate: moment()};
    
    Data.get('acc/m_lokasi/getLokasi').then(function (response) {
            $scope.listLokasi = response.data.list;
        });
    

    $scope.view = function (form) {
        Data.post(control_link + '/laporan', form).then(function (response) {
            if (response.status_code == 200) {
                $scope.data = response.data.data;
                $scope.detail = response.data.detail;
                $scope.tampilkan = true;
            } else {
                $scope.tampilkan = false;
//                toaster.pop('error', "Terjadi Kesalahan", setErrorMessage(response.errors));
            }
        });
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
        window.location = "api/acc/l_jurnal_umum/exportExcel?" + $.param(form);
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