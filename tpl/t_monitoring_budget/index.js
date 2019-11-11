app.controller('MonitoringBudgettingCtrl', function($scope, Data, $rootScope, $uibModal, Upload) {
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
    Data.get('site/base_url').then(function(response) {
        $scope.url = response.data;
    });
    $scope.modal = function(form) {
        form.tahun = $scope.form.tahun;
        var modalInstance = $uibModal.open({
            templateUrl: $scope.url.base_url + "api/" + $scope.url.acc_dir + "/tpl/t_monitoring_budget/modalDetail.html",
            controller: "modalDetailCtrl",
            size: "lg",
            backdrop: "static",
            keyboard: false,
            resolve: {
                form: form,
            }
        });
        modalInstance.result.then(function(response) {
            if (response.data == undefined) {} else {}
        });
    }
    $scope.filterTahun = function(tahun) {
        $scope.form.tahun = tahun;
        $scope.callServer(tableStateRef);
    }
    $scope.master = master;
    $scope.callServer = function callServer(tableState) {
        tableStateRef = tableState;
        $scope.isLoading = true;
        var offset = tableState.pagination.start || 0;
        var limit = tableState.pagination.number || 10;
        /** set offset and limit */
        var param = {
            tahun: $scope.form.tahun
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
});
app.controller("modalDetailCtrl", function($state, $scope, Data, $uibModalInstance, $rootScope, form) {
    $scope.form = form;
    $scope.total = 0;
    $scope.totalKegiatan = 0;
    Data.get('site/base_url').then(function(response) {
        $scope.url = response.data;
    });
    Data.get("acc/t_monitoring_budget/getDetail", {lokasi_id : $scope.form.id, tahun: $scope.form.tahun}).then(function(result) {
        $scope.listDetail = result.data.list;
        $scope.total = result.data.total;
        $scope.totalKegiatan = result.data.totalKegiatan;
    });

    $scope.exportDetail = function(){
        console.log($scope.url);
        var param = {lokasi_id : $scope.form.id, tahun: moment($scope.form.tahun).format('YYYY-MM-DD'), is_export :1};
        window.open($scope.url.base_url + "api/acc/t_monitoring_budget/getDetail?" + $.param(param), "_blank");
        /*Data.get("acc/t_monitoring_budget/getDetail", {lokasi_id : $scope.form.id, tahun: $scope.form.tahun, is_export : 1}).then(function(result) {
            $scope.listDetail = result.data.list;
            $scope.total = result.data.total;
            $scope.totalKegiatan = result.data.totalKegiatan;
        });*/
    }
    $scope.close = function() {
        $uibModalInstance.close({});
    };
});