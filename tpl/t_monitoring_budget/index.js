app.controller('lokasiCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/t_monitoring_budget";
    var master = 'Monitoring Budget';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.is_create = false;
    $scope.form = {};
    
    $scope.form.tahun = new Date();
    
    $scope.filterTahun = function (tahun) {
        $scope.form.tahun = tahun;
        $scope.callServer(tableStateRef)
    }

    $scope.master = master;
    $scope.callServer = function callServer(tableState) {
        tableStateRef = tableState;
        $scope.isLoading = true;
        var offset = tableState.pagination.start || 0;
        var limit = tableState.pagination.number || 10;

        /** set offset and limit */
        var param = {
            tahun : $scope.form.tahun
//            offset: offset,
//            limit: limit
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
        Data.get(control_link + '/index', param).then(function (response) {
            $scope.displayed = response.data.list;
            $scope.base_url = response.data.base_url;
            tableState.pagination.numberOfPages = Math.ceil(
                    response.data.totalItems / limit
                    );
        });
        $scope.isLoading = false;
    };

    /** view */
    $scope.view = function (form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_create = false;
        $scope.formtitle = master + " | Lihat Data : " + form.nama;
        $scope.form = form;
    };

});