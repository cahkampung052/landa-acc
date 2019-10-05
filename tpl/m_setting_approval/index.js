app.controller("msettingapprovalCtrl", function($scope, Data, $rootScope) {
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
    $scope.loading = false;
    Data.get("/acc/appuser/getAll").then(function(response) {
        $scope.listUser = response.data;
    });
    /**
     * End inialisasi
     */
    $scope.callServer = function callServer(tableState) {
        tableStateRef = tableState;
        $scope.isLoading = true;
        var param = {};
        if (tableState.sort.predicate) {
            param["sort"] = tableState.sort.predicate;
            param["order"] = tableState.sort.reverse;
        }
        if (tableState.search.predicateObject) {
            param["filter"] = tableState.search.predicateObject;
        }
        Data.get("acc/appapproval/index", param).then(function(response) {
            $scope.displayed = response.data.list;
            tableState.pagination.numberOfPages = Math.ceil(response.data.totalItems / limit);
        });
        $scope.isLoading = false;
    };
    $scope.listDetail = [{}];
    $scope.addDetail = function(val) {
        var comArr = eval(val);
        var newDet = {
            acc_m_user_id: {
                id: $scope.listUser[0].id,
                nama: $scope.listUser[0].nama
            },
            level: 1
        };
        val.push(newDet);
    };
    $scope.removeDetail = function(val, paramindex) {
        var comArr = eval(val);
        if (comArr.length > 1) {
            val.splice(paramindex, 1);
        } else {
            alert("Something gone wrong");
        }
    };
    $scope.create = function(form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.formtittle = "Form Tambah Data";
        $scope.form = {};
        $scope.form.status = "create";
        $scope.form.tipe = "Budgeting";
        $scope.listDetail = [{
            acc_m_user_id: {
                id: $scope.listUser[0].id,
                nama: $scope.listUser[0].nama
            },
            level: 1
        }];
    };
    $scope.update = function(form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.formtittle = "Edit Data : " + form.min;
        $scope.form = form;
        $scope.form.status = "update";
        $scope.listDetail = form.detail;
    };
    $scope.view = function(form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.formtittle = "Lihat Data : " + form.min;
        $scope.form = form;
        $scope.listDetail = form.detail;
    };
    $scope.save = function(form) {
        $scope.loading = true;
        var form = {
            data: form,
            detail: $scope.listDetail
        }
        Data.post("acc/appapproval/save", form).then(function(result) {
            if (result.status_code == 200) {
                $scope.form.id = result.data.id;
                $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                $scope.cancel();
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
            }
            $scope.loading = false;
        });
    };
    $scope.cancel = function() {
        $scope.is_edit = false;
        $scope.is_view = false;
        $scope.is_create = false;
        $scope.callServer(tableStateRef);
    };
    $scope.delete = function(row) {
        if (confirm("Apa anda yakin akan Menghapus item ini ?")) {
            row.is_deleted = 0;
            Data.post("acc/appapproval/hapus", row).then(function(result) {
                $scope.displayed.splice($scope.displayed.indexOf(row), 1);
            });
        }
    };
});