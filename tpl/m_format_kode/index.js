app.controller('formatkodeCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/m_format_kode";
    var master = 'Pengaturan';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.form = {};
    $scope.form.reset_kode = 'tahunan';
    $scope.is_edit = false;
    $scope.is_view = false;

    $scope.master = master;
    
    Data.get(control_link + '/index').then(function (response) {
            $scope.form = response.data.list;
            $scope.form.tanggal = new Date($scope.form.tanggal);
            $scope.base_url = response.data.base_url;
            if ($scope.form.reset_kode == undefined || $scope.form.reset_kode == null || $scope.form.reset_kode == '') {
                $scope.form.reset_kode = 'tahunan';
            }
    });
    
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
            $scope.form = response.data.list;
            $scope.base_url = response.data.base_url;
        });
        $scope.isLoading = false;
    };

    /** save action */
    $scope.save = function () {
//        var url = (form.id > 0) ? '/update' : '/create';
        Data.post(control_link + '/save', $scope.form).then(function (result) {
            if (result.status_code == 200) {
                $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
//                $scope.cancel();
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
            }
        });
    };
});