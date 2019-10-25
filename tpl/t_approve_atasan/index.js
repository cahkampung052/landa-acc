app.controller("tapprovalCtrl", function ($scope, Data, $rootScope, $uibModal) {
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
    var master = "Approve Proposal";
    $scope.master = master;
    $scope.cari = {
        periode: {
            endDate: moment().add(1, 'M'),
            startDate: moment().subtract(1, 'M')
        }
    };

    Data.get('site/base_url').then(function (response) {
        $scope.url = response.data;
    });

    $scope.filterTanggal = function () {
        $scope.callServer(tableStateRef);
    }
    /*
     * Ambil akun untuk detail
     */
    Data.get('acc/m_akun/akunDetail').then(function (data) {
        $scope.akunDetail = data.data.list;
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
            limit: limit,
            start_date: moment($scope.cari.periode.startDate).format("YYYY-MM-DD"),
            end_date: moment($scope.cari.periode.endDate).format("YYYY-MM-DD")
        };
        if (tableState.sort.predicate) {
            param["sort"] = tableState.sort.predicate;
            param["order"] = tableState.sort.reverse;
        }
        if (tableState.search.predicateObject) {
            param["filter"] = tableState.search.predicateObject;
        }
        param["type"] = "approve";
        Data.get("acc/apppengajuan/listapprove", param).then(function (response) {
            $scope.displayed = response.data.list;
            tableState.pagination.numberOfPages = Math.ceil(response.data.totalItems / limit);
        });
        $scope.isLoading = false;
    };
    $scope.getDetail = function (id) {
        Data.get("acc/apppengajuan/view?t_pengajuan_id=" + id).then(function (response) {
            $scope.listDetail = response.data;
        });
    };
    $scope.getAcc = function (id) {
        Data.get("acc/apppengajuan/getAcc?t_pengajuan_id=" + id).then(function (response) {
            $scope.listAcc = response.data;
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
    $scope.create = function (form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.formtittle = "Form Tambah Data";
        $scope.form = {};
    };
    $scope.update = function (form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.formtittle = "Edit Data : " + form.no_urut;
        $scope.form = form;
        $scope.getDetail(form.id);
    };
    $scope.view = function (form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.formtittle = "Lihat Data : " + form.no_proposal;
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal);
        $scope.getDetail(form.id);
        $scope.getAcc(form.id);
        $scope.cekBudget = true;
    };
    $scope.save = function (form, status) {
        var param = {
            status: status,
            data: form,
            detail : $scope.listDetail
        }
        if (status == 'rejected') {
            var foo = prompt('Alasan ditolak : ')
            if (foo) {
                param['catatan'] = foo;
                Data.post("acc/apppengajuan/status", param).then(function (result) {
                    $scope.cancel();
                });
            }
        } else {
            Data.post("acc/apppengajuan/status", param).then(function (result) {
                $scope.cancel();
            });
        }
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
            Data.post("acc/appapproveatasan/hapus", row).then(function (result) {
                $scope.displayed.splice($scope.displayed.indexOf(row), 1);
            });
        }
    };
    $scope.modalBudget = function (form) {
        $scope.cekBudget = true;
        var param = {
            tahun: moment(form.tanggal).format("YYYY"),
            m_lokasi_id: form.m_lokasi_id.id,
            nama: form.m_lokasi_id.kode + " - " + form.m_lokasi_id.nama
        };
        var modalInstance = $uibModal.open({
            templateUrl: $rootScope.pathModulAcc + "tpl/t_approve_atasan/modal.html",
            controller: "budgetCtrl",
            size: "md",
            backdrop: "static",
            keyboard: false,
            resolve: {
                form: param,
            }
        });
        modalInstance.result.then(function (response) {
            if (response.data == undefined) {
            } else {
            }
        });
    }

    $scope.modalDetail = function (form, index) {
        var modalInstance = $uibModal.open({
            templateUrl: $scope.url.base_url + "api/" + $scope.url.acc_dir + "/tpl/t_pengajuan/detail.html",
            controller: "modalDetailCtrl",
            size: "lg",
            backdrop: "static",
            keyboard: false,
            resolve: {
                form: form,
                data: {
                    is_view: $scope.is_view,
                    status: $scope.form.status
                }
            }
        })
        modalInstance.result.then(function (response) {
            console.log(response);
            if (response.detail.length > 0) {
                form.detail = response.detail;
            }
            form.harga_satuan = response.total;
            form.jumlah = 1;
            $scope.sumTotal();
        })
    }
});
app.controller("budgetCtrl", function ($state, $scope, Data, $uibModalInstance, form, $rootScope) {
    $scope.form = form;
    $scope.listBudget = [];
    Data.get('acc/m_akun/getBudgetPerLokasi', form).then(function (result) {
        $scope.listBudget = result.data;
        console.log($scope.listBudget)
    });
    $scope.close = function () {
        $uibModalInstance.close({
            'data': undefined
        });
    };
});

app.controller("modalDetailCtrl", function ($state, $scope, Data, $uibModalInstance, $rootScope, form, data) {
    $scope.form = {};
    $scope.form = form;
    $scope.lihatDariApprove = true;
    $scope.status = data.status;
    $scope.listDetail = [];
    if ($scope.form.detail != undefined) {
        $scope.listDetail = $scope.form.detail;
        //        $scope.sumTotal();
    }
    //    $scope.sumTotal();
    $scope.addDetail = function (val) {
        var comArr = eval(val);
        var newDet = {
            keterangan: "",
            budget: 0,
            realisasi: 0
        };
        val.push(newDet);
    };
    $scope.removeDetail = function (val, paramindex) {
        var comArr = eval(val);
        val.splice(paramindex, 1);
        $scope.sumTotal();
    };
    $scope.sumTotal = function () {
        var total = 0;
        angular.forEach($scope.listDetail, function (value, key) {
            if (value.budget === undefined) {
                value.budget = 0;
            }
            if (value.realisasi === undefined) {
                value.realisasi = 0;
            }
            total += value.budget;
        });
        $scope.form.sub_total = total;
    };
    $scope.save = function () {
        if ($scope.status == "Terbayar") {
            Data.post("acc/apppengajuan/saveDetail", $scope.listDetail).then(function (result) {
                if (result.status_code == 200) {
                    $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                    $scope.close();
                } else {
                    $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
                }
            });
        } else {
            $scope.close();
        }
    }
    $scope.close = function () {
        $uibModalInstance.close({
            detail: $scope.listDetail,
            total: $scope.form.sub_total
        });
    };
});