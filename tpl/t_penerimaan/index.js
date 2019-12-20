app.controller('penerimaanCtrl', function ($scope, Data, $rootScope, $uibModal, Upload, FileUploader) {
    var tableStateRef;
    var control_link = "acc/t_penerimaan";
    var master = 'Transaksi Penerimaan';
    $scope.master = master;
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.gambar = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.is_setting = false;
    $scope.urlfoto = "api/file/penerimaan/";
    
    /*
     * SETTING FIELD
     */
    $scope.checklist = false;
    $scope.field = [];
    $scope.startFrom = [];
    $scope.limit = 0;
    $scope.row = 4;
    $scope.classrow = 12/$scope.row;
    $scope.setPosition = function ($event, key, vals) {
        $event.preventDefault();
        $event.stopPropagation();
        console.log($event.keyCode)
        var ps = $scope.limit;
        if ($event.keyCode == 37) {
            ps = -($scope.limit);
        } else if ($event.keyCode == 38) {
            ps = -1;
        } else if ($event.keyCode == 40) {
            ps = 1;
        }
        console.log(ps)

        if ($event.keyCode == 37 || $event.keyCode == 39 || $event.keyCode == 38 || $event.keyCode == 40) {
            $event.preventDefault();
            var sw = $scope.field[key + ps].value;
            var chk = $scope.field[key + ps].checkbox;
            var al = $scope.field[key + ps].alias;
            $scope.field[key + ps].value = vals.value;
            $scope.field[key + ps].checkbox = vals.checkbox;
            $scope.field[key + ps].alias = vals.alias;
            $scope.field[key].value = sw;
            $scope.field[key].checkbox = chk;
            $scope.field[key].alias = al;
            var f = key + ps;
            setTimeout(function () {
                $('.input-' + f).focus()
            }, 1)
        } else {
            console.log(vals)
            console.log($event.keyCode)
            $scope.field[key].alias = vals.alias;
        }

//        console.log($scope.field)

    }

    $scope.fillCheckBox = function (a) {
        console.log(a)
        angular.forEach($scope.field, function (val, key) {
            val.checkbox = a;
        })
    }

    $scope.savePosition = function () {
        Data.post(control_link + '/savePosition', $scope.field).then(function (result) {
            if (result.status_code == 200) {
//                $scope.is_setting = false;
                $scope.callServer(tableStateRef)
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
            }
        });
    }
    /*
     * END SETTING FIELD
     */

    Data.get('acc/m_lokasi/default_lokasi').then(function (data) {
        $scope.lokasi_default = data.data;
    });

    Data.get('acc/m_akun/akunKas').then(function (data) {
        $scope.akun = data.data.list;
    });
    Data.get('acc/m_akun/akunDetail').then(function (data) {
        $scope.akunDetail = data.data.list;
    });
    $scope.cariKontak = function (cari) {
        if (cari.toString().length > 2) {
            Data.get('acc/m_customer/getKontak', {
                nama: cari
            }).then(function (response) {
                $scope.listSupplier = response.data.list;
            });
        }
    };
    Data.get('acc/m_lokasi/getLokasi').then(function (response) {
        $scope.listLokasi = response.data.list;
    });
    Data.get('acc/m_akun/getTanggalSetting').then(function (response) {
        $scope.tanggal_setting = response.data.tanggal;
        $scope.options = {
            minDate: new Date(response.data.tanggal),
        };
    });
    /**
     * Upload Gambar
     */
    var uploader = $scope.uploader = new FileUploader({
        url: Data.base + 'acc/t_penerimaan/upload/bukti',
        formData: [],
        removeAfterUpload: true,
    });
    $scope.uploadGambar = function () {
        $scope.uploader.uploadAll();
    };
    uploader.filters.push({
        name: 'imageFilter',
        fn: function (item) {
            var type = '|' + item.type.slice(item.type.lastIndexOf('/') + 1) + '|';
            var x = '|jpg|png|jpeg|bmp|gif|'.indexOf(type) !== -1;
            if (!x) {
                $rootScope.alert("Terjadi Kesalahan", "Jenis gambar tidak sesuai", "error");
            }
            return x;
        }
    });
    uploader.filters.push({
        name: 'sizeFilter',
        fn: function (item) {
            var xz = item.size < 2097152;
            if (!xz) {
                $rootScope.alert("Terjadi Kesalahan", "Ukuran gambar tidak boleh lebih dari 2MB", "error");
            }
            return xz;
        }
    });
    //    uploader.onSuccessItem = function(fileItem, response) {
    //        if (response.answer == 'File transfer completed') {
    //            var d = new Date();
    //            $scope.gambar.unshift({
    //                img: response.img,
    //                id: response.id
    //            });
    //            $scope.urlgambar = "api/file/penerimaan/" + d.getFullYear() + "/" + (d.getMonth() + 1) + "/";
    //        }
    //    };
    uploader.onBeforeUploadItem = function (item) {
        item.formData.push({
            id: $scope.form.id,
        });
    };
    $scope.removeFoto = function (paramindex, namaFoto, pid) {
        Data.post('acc/t_penerimaan/removegambar', {
            id: pid,
            img: namaFoto
        }).then(function (data) {
            $scope.gambar.splice(paramindex, 1);
        });
    };
    $scope.gambarzoom = function (img) {
        var modalInstance = $uibModal.open({
            template: '<center><img src="' + $scope.urlfoto + img + '" class="img-fluid" ></center>',
            size: 'md',
        });
    };
    $scope.listgambar = function (id) {
        Data.get('acc/t_penerimaan/listgambar/' + id).then(function (data) {
            $scope.gambar = data.data.model;
        });
    };
    /**
     * Ambil detail
     */
    $scope.getDetail = function (id) {
        var data = {
            id: id
        }
        Data.get(control_link + '/getDetail', data).then(function (data) {
            $scope.listDetail = data.data.list;
        });
    }
    /**
     * Tambah detail
     */
    $scope.addDetail = function (val) {
        var comArr = $(".tabletr").last().index() + 1
        var newDet = {
            m_akun_id: {
                id: $scope.akunDetail[0].id,
                kode: $scope.akunDetail[0].kode,
                nama: $scope.akunDetail[0].nama
            },
            m_lokasi_id: val[0].m_lokasi_id,
            keterangan: '',
            kredit: 0,
            is_label: false,
        };
        $scope.sumTotal();
        val.splice(comArr, 0, newDet);
    };
    /**
     * Hapus detail
     */
    $scope.removeDetail = function (val, paramindex) {
        var comArr = eval(val);
        if (comArr.length > 1) {
            val.splice(paramindex, 1);
            $scope.sumTotal();
        } else {
            alert("Something gone wrong");
        }
    };
    /**
     * Kalkulasi total detail
     */
    $scope.sumTotal = function () {
        var totalkredit = 0;
        angular.forEach($scope.listDetail, function (value, key) {
            totalkredit += parseInt(value.kredit);
        });
        $scope.form.subtotal = totalkredit;
        if ($scope.form.is_ppn)
            $scope.form.ppn = (10 / 100) * totalkredit;
        else
            $scope.form.ppn = 0;
        $scope.form.total = $scope.form.ppn + totalkredit;
    };
    /*
     * empty ui-select
     */
    $scope.emptyUi = function (ui) {
        $scope.form.m_kontak_id = [];
    }
    $scope.callServer = function callServer(tableState) {
        $scope.field = [];
        tableStateRef = tableState;
        $scope.isLoading = true;
        var offset = tableState.pagination.start || 0;
        var limit = tableState.pagination.number || 1000;
        /** 
         * set offset and limit
         */
        var param = {
            offset: offset,
            limit: limit
        };
        /** 
         * set sort and order
         */
        if (tableState.sort.predicate) {
            param['sort'] = tableState.sort.predicate;
            param['order'] = tableState.sort.reverse;
        }
        /** 
         * set filter
         */
        if (tableState.search.predicateObject) {
            param['filter'] = tableState.search.predicateObject;
        }
        Data.get('acc/m_lokasi/getLokasi', param).then(function (response) {
            $scope.listLokasi = response.data.list;
        });
        Data.get(control_link + '/index', param).then(function (response) {
            $scope.displayed = response.data.list;
            if (response.data.field != undefined && response.data.field.length > 0) {
                $scope.field = response.data.field;
            } else {
                var index = 0;
                angular.forEach(response.data.list[0], function (val, key) {
                    $scope.field.push({
                        checkbox: true,
                        value: key,
                        alias: key,
                        no: index
                    });
                    index += 1;
                });
            }
            $scope.limit = Math.ceil($scope.field.length / $scope.row);
            $scope.startFrom = [];
            angular.forEach($scope.field, function (val, key) {
                if (val.no % $scope.limit == 0) {
                    $scope.startFrom.push({start: val.no, limit: $scope.limit})
                }
            })

            $scope.base_url = response.data.base_url;
            tableState.pagination.numberOfPages = Math.ceil(response.data.totalItems / limit);
        });
        $scope.isLoading = false;
    };
    /** 
     * create
     */
    $scope.create = function () {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.is_disable = false;
        $scope.formtitle = master + " | Form Tambah Data";
        $scope.form = {};
        // if ($scope.lokasi_default.lokasi_pemasukan != 0)
        //     $scope.form.m_lokasi_id = $scope.lokasi_default.lokasi_pemasukan;
        $scope.form.ppn = 0;
        $scope.form.is_ppn = false;
        $scope.form.tanggal = new Date($scope.tanggal_setting);
        if (new Date() >= new Date($scope.tanggal_setting)) {
            $scope.form.tanggal = new Date();
        }
        $scope.listDetail = [{
                m_akun_id: {
                    id: $scope.akunDetail[0].id,
                    kode: $scope.akunDetail[0].kode,
                    nama: $scope.akunDetail[0].nama
                },
                m_lokasi_id: {
                    id: $scope.listLokasi[0].id,
                    nama: $scope.listLokasi[0].nama,
                    kode: $scope.listLokasi[0].kode
                },
                kredit: 0
            }];
        $scope.sumTotal();
        $scope.gambar = [];
        $scope.urlfoto = "";
    };
    $scope.lokasi = function (select) {
        angular.forEach($scope.listDetail, function (val, key) {
            val.m_lokasi_id = {
                id: select.id,
                nama: select.nama
            }
        });
    };
    $scope.createDiterimaDari = function (form, index, is_view) {
        Data.get('site/base_url').then(function (response) {
            if (index == null && is_view == 0) {
                var params = {
                    is_create: 1,
                    is_view: 0
                };
            } else if (index != null && is_view == 0) {
                var params = $scope.form.listKorban[index];
                params.is_create = 0;
                params.is_view = 0;
            } else if (index != null && is_view == 1) {
                var params = $scope.form.listKorban[index];
                params.is_create = 0;
                params.is_view = 1;
            }
            var time = new Date().getTime();
            var modalInstance = $uibModal.open({
                templateUrl: response.data.base_url + "api/acc/landaacc/tpl/t_penerimaan/modal_diterima_dari.html?" + time,
                controller: "modalDiterimaDari",
                size: "lg",
                backdrop: "static",
                keyboard: false,
                resolve: {
                    'form': params,
                }
            });
            modalInstance.result.then(function (result) {
                Data.get("t_booking/getCustomer").then(function (response) {
                    $scope.listCustomer = response.data;
                });
            }, function () {});
        });
    };
    /** 
     * update
     */
    $scope.update = function (form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_update = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Edit Data : " + form.no_transaksi;
        $scope.form = form;
        $scope.form.is_ppn = false;
//        if ($scope.form.ppn > 0) {
//            $scope.form.is_ppn = true;
//        }
        $scope.form.subtotal = $scope.form.total;
        $scope.form.total = parseInt($scope.form.total) + parseInt($scope.form.ppn);
        $scope.form.tanggal = new Date(form.tanggal2);
        $scope.tanggal_foto = new Date(form.tanggal);
        $scope.getDetail(form.id);
        $scope.listgambar(form.id);
        $scope.urlfoto += $scope.tanggal_foto.getFullYear() + "/" + (parseInt($scope.tanggal_foto.getMonth()) + 1) + "/";
    };
    /** 
     * view
     */
    $scope.view = function (form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_disable = true;
        $scope.formtitle = master + " | Lihat Data : " + form.no_transaksi;
        $scope.form = form;
        $scope.form.is_ppn = false;
        if ($scope.form.ppn > 0) {
            $scope.form.is_ppn = true;
        }
        $scope.form.subtotal = $scope.form.total;
        $scope.form.total = parseInt($scope.form.total) + parseInt($scope.form.ppn);
        $scope.form.tanggal = new Date(form.tanggal2);
        $scope.tanggal_foto = new Date(form.tanggal);
        $scope.getDetail(form.id);
        $scope.listgambar(form.id);
        $scope.urlfoto += $scope.tanggal_foto.getFullYear() + "/" + (parseInt($scope.tanggal_foto.getMonth()) + 1) + "/";
    };
    /**
     * save action
     */
    $scope.save = function (form, type_save) {
        form["status"] = type_save;
        var data = {
            form: form,
            detail: $scope.listDetail,
        }
        Data.post(control_link + '/save', data).then(function (result) {
            if (result.status_code == 200) {
                $scope.form.id = result.data.id;
                $scope.uploadGambar();
                $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                $scope.cancel();
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
            }
        });
    };
    /** 
     * cancel action
     */
    $scope.cancel = function () {
        if (!$scope.is_view) {
            $scope.callServer(tableStateRef);
        }
        $scope.is_edit = false;
        $scope.is_view = false;
        $scope.urlfoto = "api/file/penerimaan/";
    };
    /**
     * Hapus transaksi
     */
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
                    Swal.fire({
                        title: "Terhapus",
                        text: "Data Berhasil Di Hapus Permanen.",
                        type: "success"
                    }).then(function () {
                        $scope.cancel();
                    });
                });
            }
        });
    };
    /*
     * print
     */
    $scope.print = function (row) {
        var data = angular.copy(row);
        Data.get('site/base_url').then(function (response) {
            //                console.log(response)
            window.open(response.data.base_url + "api/acc/t_penerimaan/print?" + $.param(row), "_blank");
        });
    };
    /**
     * Modal setting template print
     */
    $scope.modalSetting = function () {
        Data.get('site/base_url').then(function (response) {
            var modalInstance = $uibModal.open({
                templateUrl: response.data.base_url + "api/" + response.data.acc_dir + "/tpl/t_penerimaan/modal.html",
                controller: "settingPrintCtrl",
                size: "xl",
                backdrop: "static",
                keyboard: false,
            });
            modalInstance.result.then(function (response) {
                if (response.data == undefined) {
                } else {
                }
            });
        });
    }
});
app.controller("settingPrintCtrl", function ($state, $scope, Data, $uibModalInstance, $rootScope) {
    $scope.templateDefault = "";
    Data.get("acc/t_penerimaan/getTemplate").then(function (response) {
        $scope.templateDefault = response.data;
    });
    $scope.close = function () {
        $uibModalInstance.close({
            'data': undefined
        });
    };
    $scope.save = function () {
        var ckeditor_data = CKEDITOR.instances.editor1.getData();
        var params = {
            print_penerimaan: ckeditor_data
        };
        Data.post("acc/t_penerimaan/saveTemplate", params).then(function (result) {
            if (result.status_code == 200) {
                $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                $scope.close();
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
            }
        });
    }
});
app.controller("modalDiterimaDari", function ($state, $scope, Data, $uibModalInstance, form, $rootScope) {
    $scope.form = form;
    $scope.form.type = 'individu';
    $scope.form.jenis = 'lain';
    var control_link = "m_customer";
    Data.get("m_perusahaan/index").then(function (response) {
        $scope.listPerusahaan = response.data.list;
        $scope.form.perusahaan_id = $scope.listPerusahaan[0];
    });

    $scope.save = function (form) {
        $scope.loading = true;
        Data.post(control_link + "/save", form).then(function (result) {
            if (result.status_code == 200) {
                $rootScope.alert("Berhasil", "Data berhasil tersimpan", "success");
                $scope.close(form);
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
            }
            $scope.loading = false;
        });
    };
    $scope.close = function (listKorban) {
        $uibModalInstance.close({
            'data': listKorban
        });
    };
});