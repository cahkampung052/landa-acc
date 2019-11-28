app.controller('pembayaranpiutangCtrl', function ($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/t_pembayaran_piutang";
    var master = 'Transaksi Pembayaran Piutang';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.form = {};
    $scope.data = {};
    $scope.listJurnal = [];
    $scope.form.tanggal2 = {
        endDate: moment().add(1, 'D'),
        startDate: moment()
    };
    $scope.url = "";

    $scope.dateOptions = {
//        minMode: 'year'
    };

    Data.get('site/base_url').then(function (response) {
        $scope.url = response.data.base_url;
    });
    Data.get("acc/m_lokasi/getLokasi").then(function (result) {
        $scope.listLokasi = result.data.list;
    });

    /*
     * ambil data di load
     */
    Data.get('acc/m_akun/akunKas').then(function (data) {
        $scope.listAkun = data.data.list;
    });

    /*
     * ambil pemetaan akun potongan pembelian
     */
    Data.get('acc/m_akun_peta/getPemetaanAkun', {type: "Potongan Pembelian"}).then(function (data) {
        $scope.akunPotongan = data.data.list[0];
    });

    $scope.getSupplier = function (val) {
        var param = {nama: val};
        Data.get("acc/m_customer/getCustomer", param).then(function (response) {
            $scope.listCustomer = response.data.list;
        });
    };
    /*
     * end
     */

    $scope.resetFilter = function (filter) {
        $scope.form[filter] = undefined;
        $scope.onFilter($scope.form);
    }

    $scope.onFilter = function (val) {
        $scope.callServer(tableStateRef);
    }

    /*
     * detail
     */
    $scope.getListPiutang = function (supplier_id, lokasi_id) {

        if ((customer_id != undefined && customer_id != '') && (lokasi_id != undefined && lokasi_id != '')) {

            var data = {
                customer_id: customer_id,
                lokasi_id: lokasi_id
            };
            Data.get("acc/t_pembayaran_piutang/getListPiutang", data).then(function (response) {
                $scope.detPiutang = response.data;
                $scope.kalkulasi();
            });
        }

    };

    $scope.kalkulasi = function () {
        var totalBayar = 0;
        $scope.form.totalPotongan = 0;
        $scope.form.denda == undefined ? 0 : parseInt($scope.form.denda);
        angular.forEach($scope.detHutang, function (value, key) {
            value.bayar = value.bayar == undefined ? 0 : parseInt(value.bayar);
            value.potongan = value.potongan == undefined ? 0 : parseInt(value.potongan);
            totalBayar += value.bayar;
            $scope.form.totalPotongan += value.potongan;
        });
        // $scope.form.total_bayar = parseInt(totalBayar) - parseInt($scope.form.totalPotongan);
        $scope.form.total_bayar = parseInt(totalBayar);
        $scope.form.totalBayar =
                parseInt(totalBayar) +
                parseInt($scope.form.denda) -
                parseInt($scope.form.totalPotongan);
        $scope.form.totalPiutang = parseInt($scope.form.total_bayar);
        $scope.form.total = parseInt($scope.form.totalBayar);

        $scope.prepareJurnal();
    };

    $scope.bayarIni = function (index, cek) {

        if (cek == true) {
            $scope.detPiutang[index].bayar = $scope.detPiutang[index].sisa;
        } else {
            $scope.detPiutang[index].bayar = 0;
        }
    }

    $scope.getDetail = function (id) {
        Data.get("acc/t_pembayaran_piutang/view?id=" + id).then(function (response) {
            $scope.form.m_akun_denda_id = response.data.akun_denda;
            $scope.detPiutang = response.data.detail;
            $scope.kalkulasi();
        });
    };

    $scope.removeRow = function (paramindex) {
        var comArr = eval($scope.detPiutang);
        if (comArr.length > 1) {
            $scope.detPiutang.splice(paramindex, 1);
            $scope.kalkulasi();
        } else {
            alert("Something gone wrong");
        }
    };

    $scope.prepareJurnal = function () {
        console.log("ok")
        console.log($scope.detPiutang)
        var listJurnal = [];
        var index = 0;
        var total = 0;
        var keterangan = "";
        var keterangan_potongan = "";
        var potongan = 0;
        angular.forEach($scope.detPiutang, function (val, key) {
            if (val.akun_hutang != undefined && val.bayar > 0) {
                if (index > 0) {
                    if (listJurnal[index - 1] && listJurnal[index - 1].akun.id == val.akun_hutang.id) {
                        listJurnal[index - 1].debit += val.bayar;
                        listJurnal[index - 1].keterangan += "</br>Pembayaran Piutang (" + val.kode + ")";
                    } else {
                        listJurnal[index] = {
                            akun: val.akun_piutang,
                            tipe: "debit",
                            debit: val.bayar,
                            kredit: 0,
                            keterangan: "Pembayaran Piutang (" + val.kode + ")",
                            lokasi: val.m_lokasi_id
                        }
                        index++;
                    }
                } else {
                    listJurnal[index] = {
                        akun: val.akun_piutang,
                        tipe: "debit",
                        debit: val.bayar,
                        kredit: 0,
                        keterangan: "Pembayaran Piutang (" + val.kode + ")",
                        lokasi: val.m_lokasi_id
                    }
                    index++;
                }
                potongan += val.potongan;
                total += val.bayar;
                keterangan += "Pembayaran Piutang (" + val.kode + ")</br>";
            }


        });
        if (potongan > 0) {
            listJurnal[index] = {
                akun: $scope.akunPotongan,
                tipe: "kredit",
                debit: 0,
                kredit: potongan,
                keterangan: "Potongan Pembayaran Piutang"
            }
            total -= potongan;
            index++;
        }
        if ($scope.form.akun != undefined) {
            listJurnal[index] = {
                akun: $scope.form.akun,
                tipe: "kredit",
                debit: 0,
                kredit: total,
                keterangan: keterangan
            }
        }
        console.log(listJurnal)
        $scope.data.totalJurnal = total + potongan;
        $scope.listJurnal = listJurnal;
    }
    /*
     * end detail
     */

    $scope.getJurnal = function (id) {
        Data.get("acc/t_pembayaran_hutang/getJurnal", {
            id: id
        }).then(function (response) {
            $scope.listJurnal = response.data.detail;
            $scope.data.totalJurnal = response.data.total.totalDebit;
        });
    };

    Data.get('acc/m_akun/getTanggalSetting').then(function (response) {

        $scope.tanggal_setting = response.data.tanggal;
        console.log($scope.tanggal_setting)

        $scope.options = {
            minDate: new Date(response.data.tanggal),
        };
    });


    $scope.master = master;
    $scope.callServer = function callServer(tableState) {
        tableStateRef = tableState;
        $scope.isLoading = true;
        var offset = tableState.pagination.start || 0;
        var limit = tableState.pagination.number || 1000;
        /** set offset and limit */
        var param = {
            limit: limit,
            offset: offset,
            m_customer_id: $scope.form.customer != undefined ? $scope.form.customer.id : undefined,
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

    /** create */
    $scope.create = function () {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.is_disable = false;
        $scope.formtitle = master + " | Form Tambah Data";
        $scope.form = {};
        $scope.form.tanggal = moment();
        $scope.form.tgl_verifikasi = {
            endDate: moment(),
            startDate: moment()
        };
        if ($scope.listAkun.length > 0) {
            $scope.form.akun = $scope.listAkun[0];
        }
        $scope.form.tanggal = new Date($scope.tanggal_setting);
        if (new Date() >= new Date($scope.tanggal_setting)) {
            $scope.form.tanggal = new Date();
        }
        $scope.detHutang = [{}];
    };
    /** update */
    $scope.update = function (form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_update = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Edit Data : " + form.kode;
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal);
        $scope.form.tgl_verifikasi = {
            startDate: form.tgl_mulai,
            endDate: form.tgl_selesai
        }
        $scope.getDetail(form.id);


    };
    /** view */
    $scope.view = function (form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_disable = true;
        $scope.is_create = false;
        $scope.is_update = false;
        $scope.formtitle = master + " | Lihat Data : " + form.kode;
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal);
        $scope.form.tgl_verifikasi = {
            startDate: form.tgl_mulai,
            endDate: form.tgl_selesai
        }
        $scope.getDetail(form.id);
        if (form.status == "Terposting") {
            $scope.getJurnal(form.id)
        }

    };
    /** save action */
    $scope.save = function (form, type_save) {
        if (($scope.data.totalJurnal != $scope.form.total_bayar)) {
            $rootScope.alert(
                    "Peringatan!",
                    "Total jurnal dan total bayar tidak sama",
                    "error"
                    );
        } else {
            var go = true;
            if ($scope.detPiutang.length < 1) {
                go = false;
            }
            angular.forEach($scope.listJurnal, function (value, key) {
                if (value.akun == undefined) {
                    go = false;
                }
            });
            if (go) {
                form.status = type_save
                form.startDate = moment(form.tgl_verifikasi.startDate).format("YYYY-MM-DD")
                form.endDate = moment(form.tgl_verifikasi.endDate).format("YYYY-MM-DD")
                var data = {
                    form: form,
                    detail: $scope.detPiutang,
                    jurnal: $scope.listJurnal
                };
                Data.post("acc/t_pembayaran_piutang/save", data).then(function (result) {
                    if (result.status_code == 200) {
                        $scope.is_edit = false;
                        $scope.callServer(tableStateRef);
                        $rootScope.alert(
                                "Berhasil",
                                "Data berhasil disimpan",
                                "success"
                                );
                    } else {
                        $rootScope.alert(
                                "Terjadi Kesalahan",
                                setErrorMessage(result.errors),
                                "error"
                                );
                    }
                });
            } else {
                $rootScope.alert(
                        "Peringatan!",
                        "Jurnal / detail tidak valid, cek kembali jurnal dan detail sebelum simpan",
                        "error"
                        );
            }


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
    $scope.trash = function (row) {
        var data = angular.copy(row);
        Swal.fire({
            title: "Peringatan ! ",
            text: "Apakah Anda Yakin Ingin Menghapus Data Ini",
            type: "warning",
            showCancelButton: true,
            confirmButtonColor: "#DD6B55",
            confirmButtonText: "Iya, di Hapus",
            cancelButtonText: "Tidak",
        }).then((result) => {
            if (result.value) {
                row.is_deleted = 1;
                Data.post(control_link + '/trash', row).then(function (result) {
                    $rootScope.alert("Berhasil", "Data berhasil dihapus", "success");
                    $scope.cancel();

                });
            }
        });
    };
    $scope.restore = function (row) {
        var data = angular.copy(row);
        Swal.fire({
            title: "Peringatan ! ",
            text: "Apakah Anda Yakin Ingin Merestore Data Ini",
            type: "warning",
            showCancelButton: true,
            confirmButtonColor: "#DD6B55",
            confirmButtonText: "Iya, di Restore",
            cancelButtonText: "Tidak",
        }).then((result) => {
            if (result.value) {
                row.is_deleted = 0;
                Data.post(control_link + '/trash', row).then(function (result) {
                    $rootScope.alert("Berhasil", "Data berhasil direstore", "success");
                    $scope.cancel();

                });
            }
        });
    };
    $scope.delete = function (row) {
        var data = angular.copy(row);
        Swal.fire({
            title: "Peringatan ! ",
            text: "Apakah Anda Yakin Ingin Menghapus Permanen Data Ini",
            type: "warning",
            showCancelButton: true,
            confirmButtonColor: "#DD6B55",
            confirmButtonText: "Iya, di Hapus",
            cancelButtonText: "Tidak",
        }).then((result) => {
            if (result.value) {
                row.is_deleted = 1;
                Data.post(control_link + '/delete', row).then(function (result) {
                    $rootScope.alert("Berhasil", "Data berhasil dihapus permanen", "success");
                    $scope.cancel();

                });
            }
        });

    };

    $scope.printPiutang = function (id, tipe) {
        var param = {
            id: id,
            tipe: tipe
        }
        window.open($scope.url + "api/acc/t_pembayaran_piutang/print?" + $.param(param), "_blank");
    }

});