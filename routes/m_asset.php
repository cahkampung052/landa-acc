<?php

function validasi($data, $custom = array()) {
    $validasi = array(
        'nama' => 'required',
        'harga' => 'required',
        'lokasi' => 'required',
        'is_penyusutan' => 'required',
    );
    GUMP::set_field_name("akun_asset", "Akun Asset");
    GUMP::set_field_name("akun_akumulasi", "Akun Akumulasi Penyusutan");
    GUMP::set_field_name("akun_beban", "Akun Beban Penyusutan");
    GUMP::set_field_name("harga", "Nilai Perolehan");
    GUMP::set_field_name("is_penyusutan", "Penyusutan");
    GUMP::set_field_name("umur", "Umur Ekonomis");
    GUMP::set_field_name("persentase", "Tarif Depresiasi");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

function validasi_pelepasan($data, $custom = array()) {
    $validasi = array(
        'jenis_pelepasan' => 'required',
        'tgl_pelepasan' => 'required',
    );
    GUMP::set_field_name("jenis_pelepasan", "Jenis Pelepasan");
    GUMP::set_field_name("tgl_pelepasan", "Tanggal Pelepasan");
    GUMP::set_field_name("nilai_pelepasan", "Nilai Pelepasan");
    GUMP::set_field_name("akun_kas_pelepasan", "Akun Kas / Piutang");
    GUMP::set_field_name("akun_laba_rugi", "Akun Laba / Rugi");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->get('/acc/m_asset/cekPenyusutanBulanini', function ($request, $response) {
    $sql = $this->db;
    $y = date("Y");
    $m = date("m");

    $cek = $sql->select("*")->from("acc_riw_penyusutan")
            ->where("MONTH(periode)", "=", $m)
            ->where("YEAR(periode)", "=", $y)
            ->limit(1)
            ->findAll();
    $totalItem = $sql->count();


    return successResponse($response, $totalItem);
});

$app->get('/acc/m_asset/list_penyusutan', function ($request, $response) {
    $params = $request->getParams();
    $tableuser = tableUser();
    // $sort     = "m_akun.kode ASC";
    $offset = isset($params['offset']) ? $params['offset'] : 0;
    $limit = isset($params['limit']) ? $params['limit'] : 20;

    $db = $this->db;
    $db->select("acc_riw_penyusutan.*,acc_m_lokasi.nama as nm_lokasi," . $tableuser . ".nama as nm_user,SUM(penyusutan_perbulan) as total_penyusutan")
            ->from("acc_riw_penyusutan")
            ->leftJoin("acc_m_lokasi", "acc_m_lokasi.id = acc_riw_penyusutan.lokasi_id")
            ->leftJoin($tableuser, $tableuser . ".id = acc_riw_penyusutan.created_by")
            ->leftJoin("acc_riw_penyusutan_dt", "acc_riw_penyusutan.id = acc_riw_penyusutan_dt.riw_id")
            ->orderBy('acc_riw_penyusutan.id DESC');

    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);

        foreach ($filter as $key => $val) {
            if ($key == 'bulan') {
                $db->where("acc_riw_penyusutan.periode", 'LIKE', date("Y-m", strtotime($val)));
            } else if ($key == 'lokasi') {
                $db->where("acc_riw_penyusutan.lokasi_id", '=', $val);
            } else {
                $db->where($key, 'LIKE', $val);
            }
        }
    }

    /** Set limit */
    if (isset($params['limit']) && !empty($params['limit'])) {
        $db->limit($params['limit']);
    }

    /** Set offset */
    if (isset($params['offset']) && !empty($params['offset'])) {
        $db->offset($params['offset']);
    }
    $db->groupBy("acc_riw_penyusutan.id");
    $models = $db->findAll();
    $totalItem = $db->count();
    $setting = $db->find("SELECT * FROM acc_m_setting LIMIT 1");
    foreach ($models as $key => $value) {
        $value->periode_format = date("F Y", strtotime($value->periode));
        if (date("Y-m-t", strtotime($value->periode)) < $setting->tanggal) {
            $value->is_hidden = true;
        } else {
            $value->is_hidden = false;
        }
    }
//     print_r($models);exit();
//      print_r($arr);exit();
    return successResponse($response, [
        'list' => $models,
        'totalItems' => $totalItem,
        'base_url' => str_replace('api/', '', config('SITE_URL')),
    ]);
});

$app->get('/acc/m_asset/detail_riw_penyusutan', function ($request, $response) {
    $params = $request->getParams();


    $db = $this->db;
    $db->select("acc_riw_penyusutan_dt.*,acc_asset.nama as nm_asset")
            ->from("acc_riw_penyusutan_dt")
            ->leftJoin("acc_asset", "acc_asset.id = acc_riw_penyusutan_dt.asset_id");

    if (isset($params['id']) && !empty($params['id'])) {
        $db->where("riw_id", "=", $params['id']);
    }

    $models = $db->findAll();
    $totalItem = $db->count();

    return successResponse($response, [
        'list' => $models,
        'totalItems' => $totalItem,
        'base_url' => str_replace('api/', '', config('SITE_URL')),
    ]);
});

$app->post('/acc/m_asset/hapus_penyusutan', function ($request, $response) {
    $data = $request->getParams();
    $sql = $this->db;

    $cek_riw = $sql->select("acc_riw_penyusutan.id,acc_jurnal.id as jurnal_id")
            ->from("acc_riw_penyusutan")
            ->leftJoin("acc_jurnal", "acc_jurnal.reff_id = acc_riw_penyusutan.id AND reff_type = 'acc_riw_penyusutan'")
            ->where("acc_riw_penyusutan.id", "=", $data["id"])
            ->find();

    $delete = $sql->delete('acc_riw_penyusutan', array('id' => $cek_riw->id));
    $delete = $sql->delete('acc_riw_penyusutan_dt', array('riw_id' => $cek_riw->id));
    $delete = $sql->delete('acc_jurnal', array('id' => $cek_riw->jurnal_id));
    $delete = $sql->delete('acc_jurnal_det', array('acc_jurnal_id' => $cek_riw->jurnal_id));
    $delete = $sql->delete('acc_trans_detail', array('reff_type' => "acc_jurnal_penyusutan", 'reff_id' => $cek_riw->jurnal_id));

    if ($delete) {
        return successResponse($response, ['data berhasil dihapus']);
    } else {
        return unprocessResponse($response, ['data gagal dihapus']);
    }
});



$app->get('/acc/m_asset/tampilPenyusutan', function ($request, $response) {
    $params = $request->getParams();


    $sql = $this->db;
    $sql->select("*")->from("acc_asset")
            ->where("status", "=", 'Aktif')
            ->where("is_penyusutan", "=", 1)
            ->where("lokasi_id", "=", $params['lokasi_id'])
            ->where("tgl_mulai_penyusutan", "<", date("Y-m-t", strtotime($params['bulan'])))
            // ->where("periode_awal_penyusutan", "<", date("Y-m-t",strtotime($params['bulan'])))
            ->where("periode_akhir_penyusutan", ">", date("Y-m-t", strtotime($params['bulan'])))
    ;
    $total = 0;
    $models = $sql->findAll();
    foreach ($models as $key => $value) {
        $total += $value->penyusutan_perbulan;
    }

    return successResponse($response, [
        'list' => $models,
        'total' => $total,
        'base_url' => str_replace('api/', '', config('SITE_URL')),
    ]);
});

$app->post('/acc/m_asset/prosesPenyusutan', function ($request, $response) {

    $params = $request->getParams();
    $data = $params;
    $sql = $this->db;

    //cek riwayat penyusutan jika ada maka ditimpa
    $cek_riw = $sql->select("acc_riw_penyusutan.id,acc_jurnal.id as jurnal_id")
            ->from("acc_riw_penyusutan")
            ->leftJoin("acc_jurnal", "acc_jurnal.reff_id = acc_riw_penyusutan.id AND reff_type = 'acc_riw_penyusutan'")
            ->where("acc_riw_penyusutan.periode", "=", date("Y-m-d", strtotime($params["bulan"])))
            ->where("acc_riw_penyusutan.lokasi_id", "=", $params["form"]["lokasi"]["id"])
            ->find();
    if ($cek_riw != FALSE) {
        $delete = $sql->delete('acc_riw_penyusutan', array('id' => $cek_riw->id));
        $delete = $sql->delete('acc_riw_penyusutan_dt', array('riw_id' => $cek_riw->id));
        $delete = $sql->delete('acc_jurnal', array('id' => $cek_riw->jurnal_id));
        $delete = $sql->delete('acc_jurnal_det', array('acc_jurnal_id' => $cek_riw->jurnal_id));
        $delete = $sql->delete('acc_trans_detail', array('reff_type' => "acc_jurnal_penyusutan", 'reff_id' => $cek_riw->jurnal_id));
    }

    //insert riwayat penyusutan
    $d_riw = array(
        "periode" => date("Y-m-d", strtotime($params["bulan"])),
        "lokasi_id" => $params["form"]["lokasi"]["id"]
    );
    $insert_riw = $sql->insert("acc_riw_penyusutan", $d_riw);

    //insert jurnal
    $cek = $sql->find("select no_transaksi from acc_jurnal order by no_transaksi desc");
    $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_transaksi, -4)) + 1;
    $no_urut = substr('0000' . $urut, -4);
    $no_transaksi = $params["form"]["lokasi"]["kode"] . date("y") . "JRNL" . $no_urut;
    $keterangan = "Penyusutan " . $params["form"]["lokasi"]["nama"] . " Bulan " . date("F Y", strtotime($params["bulan"]));

    $d_jurnal = array(
        "no_transaksi" => $no_transaksi,
        "m_lokasi_id" => $params["form"]["lokasi"]["id"],
        "keterangan" => $keterangan,
        "tanggal" => date("Y-m-d", strtotime($params["bulan"])),
        "total_kredit" => $params["form"]["total"],
        "total_debit" => $params["form"]["total"],
        "reff_id" => $insert_riw->id,
        "reff_type" => 'acc_riw_penyusutan'
    );
    $insert_jurnal = $sql->insert("acc_jurnal", $d_jurnal);


    foreach ($params["listDetail"] as $key => $value) {
        $keterangan_det = "Penyusutan " . $value["nama"] . " (" . $value["no_serial"] . ") Bulan " . date("F Y", strtotime($params["bulan"]));
        //insert dt riwayat penyusutan
        $dt_riw = array(
            "riw_id" => $insert_riw->id,
            "asset_id" => $value["id"],
            "penyusutan_perbulan" => $value["penyusutan_perbulan"]
        );
        $insert_riw_dt = $sql->insert("acc_riw_penyusutan_dt", $dt_riw);

        //insert jurnal umum debit
        $dt_jurnal_deb = array(
            "acc_jurnal_id" => $insert_jurnal->id,
            "m_akun_id" => $value["akun_beban_id"],
            "m_lokasi_id" => $value["lokasi_id"],
            "debit" => $value["penyusutan_perbulan"],
            "kredit" => 0,
            "keterangan" => $keterangan_det
        );
        $insert_jurnal_dt1 = $sql->insert("acc_jurnal_det", $dt_jurnal_deb);

        //insert jurnal umum kredit
        $dt_jurnal_kredit = array(
            "acc_jurnal_id" => $insert_jurnal->id,
            "m_akun_id" => $value["akun_akumulasi_id"],
            "m_lokasi_id" => $value["lokasi_id"],
            "debit" => 0,
            "kredit" => $value["penyusutan_perbulan"],
            "keterangan" => $keterangan_det
        );
        $insert_jurnal_dt2 = $sql->insert("acc_jurnal_det", $dt_jurnal_kredit);

        //insert transdt umum debit
        $transdet_deb = array(
            "reff_id" => $insert_jurnal->id,
            "reff_type" => "acc_jurnal_penyusutan",
            "m_akun_id" => $value["akun_beban_id"],
            "m_lokasi_id" => $value["lokasi_id"],
            "debit" => $value["penyusutan_perbulan"],
            "kredit" => 0,
            "keterangan" => $keterangan_det,
            "tanggal" => date("Y-m-d", strtotime($params["bulan"])),
            "kode" => $no_transaksi
        );
        $insert_trans1 = $sql->insert("acc_trans_detail", $transdet_deb);

        //insert transdt umum kredit
        $transdet_kredit = array(
            "reff_id" => $insert_jurnal->id,
            "reff_type" => "acc_jurnal_penyusutan",
            "m_akun_id" => $value["akun_akumulasi_id"],
            "m_lokasi_id" => $value["lokasi_id"],
            "debit" => 0,
            "kredit" => $value["penyusutan_perbulan"],
            "keterangan" => $keterangan_det,
            "tanggal" => date("Y-m-d", strtotime($params["bulan"])),
            "kode" => $no_transaksi
        );
        $insert_trans2 = $sql->insert("acc_trans_detail", $transdet_kredit);
    };

    if ($insert_jurnal) {
        return successResponse($response, $insert_jurnal);
    } else {
        return unprocessResponse($response, ['Data Gagal Di Simpan']);
    }
});


$app->get('/acc/m_asset/apiPenyusutan', function ($request, $response) {

    $params = $request->getParams();
    $data = $params;
    $sql = $this->db;

    try {
        $bulan_ini = date("Y-m-d");
        //lokasi id 1 -> Yayasan UKDC
        $get_lokasi_id = 1;
        $arr_lokasi_id = [];
        if (isset($get_lokasi_id)) {
            $lokasiId = getChildId("acc_m_lokasi", $get_lokasi_id);
            /*
             * jika lokasi punya child
             */
            if (!empty($lokasiId)) {
                $lokasiId[] = $get_lokasi_id;
                $arr_lokasi_id = $lokasiId;
                $lokasiId = implode(",", $lokasiId);
            }
            /*
             * jika lokasi tidak punya child
             */ else {
                $lokasiId = $get_lokasi_id;
            }
        }

        //cek riwayat penyusutan jika ada maka ditimpa
        $cek_riw = $sql->select("acc_riw_penyusutan.id,acc_jurnal.id as jurnal_id")
                ->from("acc_riw_penyusutan")
                ->leftJoin("acc_jurnal", "acc_jurnal.reff_id = acc_riw_penyusutan.id AND reff_type = 'acc_riw_penyusutan'")
                ->where("acc_riw_penyusutan.periode", "=", $bulan_ini)
                // ->where("acc_riw_penyusutan.lokasi_id","=",$params["form"]["lokasi"]["id"])
                ->customWhere("acc_riw_penyusutan.lokasi_id IN (" . $lokasiId . ")", "AND")
                ->findAll();
        if ($cek_riw != FALSE && count($cek_riw) > 0) {
            foreach ($cek_riw as $key => $value) {
                $delete = $sql->delete('acc_riw_penyusutan', array('id' => $value->id));
                $delete = $sql->delete('acc_riw_penyusutan_dt', array('riw_id' => $value->id));
                $delete = $sql->delete('acc_jurnal', array('id' => $value->jurnal_id));
                $delete = $sql->delete('acc_jurnal_det', array('acc_jurnal_id' => $value->jurnal_id));
                $delete = $sql->delete('acc_trans_detail', array('reff_type' => "acc_jurnal_penyusutan", 'reff_id' => $value->jurnal_id));
            }
        }

        //select penyusutan
        $sql->select("acc_asset.*,acc_m_lokasi.nama as nama_lokasi,acc_m_lokasi.kode as kode_lokasi")
                ->from("acc_asset")
                ->leftJoin("acc_m_lokasi", "acc_m_lokasi.id = acc_asset.lokasi_id")
                ->where("status", "=", 'Aktif')
                ->where("is_penyusutan", "=", 1)
                // ->where("lokasi_id", "=", $params['lokasi_id'])
                ->customWhere("lokasi_id IN ($lokasiId)", "AND")
                ->where("tgl_mulai_penyusutan", "<", date("Y-m-t", strtotime($bulan_ini)))
                ->where("periode_akhir_penyusutan", ">", date("Y-m-t", strtotime($bulan_ini)))
        ;
        $total_penyusutan = [];
        $nama_lokasi = [];
        $kode_lokasi = [];
        $models = $sql->findAll();
        //hitung total penyusutan
        foreach ($models as $key => $value) {
            if (!isset($total_penyusutan[$value->lokasi_id])) {
                $total_penyusutan[$value->lokasi_id] = 0;
            }
            $total_penyusutan[$value->lokasi_id] += $value->penyusutan_perbulan;
            $nama_lokasi[$value->lokasi_id] = $value->nama_lokasi;
            $kode_lokasi[$value->lokasi_id] = $value->kode_lokasi;
        }

        $getJurnal = [];
        foreach ($arr_lokasi_id as $key => $value) {
            if (isset($total_penyusutan[$value])) {
                //insert riwayat penyusutan
                $d_riw = array(
                    "periode" => date("Y-m-d", strtotime($bulan_ini)),
                    "lokasi_id" => $value
                );
                $insert_riw = $sql->insert("acc_riw_penyusutan", $d_riw);
                $getJurnal[$value]['riw_id'] = $insert_riw->id;

                //insert jurnal
                $no_transaksi = generateNoTransaksi('jurnal', @$kode_lokasi[$value]);
                $getJurnal[$value]['no_transaksi'] = $no_transaksi;
                $keterangan = "Penyusutan " . @$nama_lokasi[$value] . " Bulan " . date("F Y", strtotime($bulan_ini));

                $d_jurnal = array(
                    "no_transaksi" => $no_transaksi,
                    "m_lokasi_id" => $value,
                    "keterangan" => $keterangan,
                    "tanggal" => date("Y-m-d", strtotime($bulan_ini)),
                    "total_kredit" => $total_penyusutan[$value],
                    "total_debit" => $total_penyusutan[$value],
                    "reff_id" => $insert_riw->id,
                    "reff_type" => 'acc_riw_penyusutan'
                );
                $insert_jurnal = $sql->insert("acc_jurnal", $d_jurnal);
                $getJurnal[$value]['jurnal_id'] = $insert_jurnal->id;
            }
        }

        $no_insert = 0;
        foreach ($models as $key => $value) {
            if (isset($getJurnal[$value->lokasi_id])) {
                $keterangan_det = "Penyusutan " . $value->nama . " (" . $value->no_serial . ") Bulan " . date("F Y", strtotime($bulan_ini));
                //insert dt riwayat penyusutan
                $dt_riw = array(
                    "riw_id" => $getJurnal[$value->lokasi_id]['riw_id'],
                    "asset_id" => $value->id,
                    "penyusutan_perbulan" => $value->penyusutan_perbulan
                );
                $insert_riw_dt = $sql->insert("acc_riw_penyusutan_dt", $dt_riw);

                if ($insert_riw_dt) {
                    $no_insert += 1;
                }

                //insert jurnal umum debit
                $dt_jurnal_deb = array(
                    "acc_jurnal_id" => $getJurnal[$value->lokasi_id]['jurnal_id'],
                    "m_akun_id" => $value->akun_beban_id,
                    "m_lokasi_id" => $value->lokasi_id,
                    "debit" => $value->penyusutan_perbulan,
                    "kredit" => 0,
                    "keterangan" => $keterangan_det
                );
                $insert_jurnal_dt1 = $sql->insert("acc_jurnal_det", $dt_jurnal_deb);

                //insert jurnal umum kredit
                $dt_jurnal_kredit = array(
                    "acc_jurnal_id" => $getJurnal[$value->lokasi_id]['jurnal_id'],
                    "m_akun_id" => $value->akun_akumulasi_id,
                    "m_lokasi_id" => $value->lokasi_id,
                    "debit" => 0,
                    "kredit" => $value->penyusutan_perbulan,
                    "keterangan" => $keterangan_det
                );
                $insert_jurnal_dt2 = $sql->insert("acc_jurnal_det", $dt_jurnal_kredit);

                //insert transdt umum debit
                $transdet_deb = array(
                    "reff_id" => $getJurnal[$value->lokasi_id]['jurnal_id'],
                    "reff_type" => "acc_jurnal_penyusutan",
                    "m_akun_id" => $value->akun_beban_id,
                    "m_lokasi_id" => $value->lokasi_id,
                    "debit" => $value->penyusutan_perbulan,
                    "kredit" => 0,
                    "keterangan" => $keterangan_det,
                    "tanggal" => date("Y-m-d", strtotime($bulan_ini)),
                    "kode" => $getJurnal[$value->lokasi_id]['no_transaksi']
                );
                $insert_trans1 = $sql->insert("acc_trans_detail", $transdet_deb);

                //insert transdt umum kredit
                $transdet_kredit = array(
                    "reff_id" => $getJurnal[$value->lokasi_id]['jurnal_id'],
                    "reff_type" => "acc_jurnal_penyusutan",
                    "m_akun_id" => $value->akun_akumulasi_id,
                    "m_lokasi_id" => $value->lokasi_id,
                    "debit" => 0,
                    "kredit" => $value->penyusutan_perbulan,
                    "keterangan" => $keterangan_det,
                    "tanggal" => date("Y-m-d", strtotime($bulan_ini)),
                    "kode" => $getJurnal[$value->lokasi_id]['no_transaksi']
                );
                $insert_trans2 = $sql->insert("acc_trans_detail", $transdet_kredit);
            }
        };

        return successResponse($response, "Total detail = " . count($models) . '| Sukses Penyusutan : ' . $no_insert);
    } catch (Exception $e) {
        die('Error "' . $e->getMessage());
    }
});

$app->get('/acc/m_asset/getDetailPenyusutan', function ($request, $response) {
    $params = $request->getParams();

    $sql = $this->db;
    $sql->select("*")->from("acc_asset")
            ->where("id", "=", $params["id"]);
    $models = $sql->find();

    $tahun = date("Y", strtotime($models->tanggal_beli));

    if ($models->status == 'Aktif') {
        $batas_tahun = $tahun + $models->tahun;
        $batas_bulan = date('m', strtotime('-1 months', strtotime($models->tanggal_beli)));
    } else {
        $batas_tahun = date("Y", strtotime($models->tgl_pelepasan));
        $batas_bulan = date('m', strtotime($models->tgl_pelepasan));
    }

    $dt = [];
    $data_update = [];
    for ($i = $tahun; $i <= $batas_tahun; $i++) {
        if ($i == $tahun) {
            $dt[$i]['saldo_awal'] = $models->harga_beli;
            $dt[$i]['awal'] = date("t M Y", strtotime($models->tgl_mulai_penyusutan));
            $dt[$i]['awal_default'] = date("Y-m-t", strtotime($models->tgl_mulai_penyusutan));

            if ($models->status == 'Aktif') {
                $dt[$i]['akhir'] = date("t M Y", strtotime($i . "-12-01"));
                $dt[$i]['akhir_default'] = date("Y-m-t", strtotime($i . "-12-01"));
            } else {
                $dt[$i]['awal_default'] = date("Y-m-d", strtotime($models->tgl_mulai_penyusutan));
                $dt[$i]['akhir'] = date("t M Y", strtotime($i . "-" . $batas_bulan . "-01"));
                $dt[$i]['akhir_default'] = date("Y-m-t", strtotime($i . "-" . $batas_bulan . "-01"));
            }

            //data_update
            $data_update["periode_awal_penyusutan"] = date("Y-m-t", strtotime($models->tanggal_beli));
        } else if ($i == $batas_tahun) {
            $dt[$i]['awal'] = date("t M Y", strtotime($i . "-01-01"));
            $dt[$i]['akhir'] = date("t M Y", strtotime($i . "-" . $batas_bulan . "-01"));

            //format
            $dt[$i]['awal_default'] = date("Y-m-d", strtotime($i . "-01-01"));
            $dt[$i]['akhir_default'] = date("Y-m-t", strtotime($i . "-" . $batas_bulan . "-01"));
            $dt[$i]['saldo_awal'] = $dt[$i - 1]['saldo_akhir'];

            //data_update
            $data_update["periode_akhir_penyusutan"] = $dt[$i]['akhir_default'];
        } else {
            $dt[$i]['awal'] = date("t M Y", strtotime($i . "-01-01"));
            $dt[$i]['akhir'] = date("t M Y", strtotime($i . "-12-01"));

            //
            $dt[$i]['awal_default'] = date("Y-m-t", strtotime($i . "-01-01"));
            $dt[$i]['akhir_default'] = date("Y-m-t", strtotime($i . "-12-01"));
            $dt[$i]['saldo_awal'] = $dt[$i - 1]['saldo_akhir'];
        }


        //date time
        $datetime1 = new DateTime($dt[$i]['awal_default']);
        $datetime2 = new DateTime($dt[$i]['akhir_default']);
        $interval = $datetime1->diff($datetime2);
        $dt[$i]['selisih'] = $interval->m + 1;
        $dt[$i]['persentase'] = $models->persentase;

        $dt[$i]['penyusutan_pertahun'] = round($dt[$i]['selisih'] / 12 * ($dt[$tahun]['saldo_awal'] - $models->nilai_residu) * ($models->persentase / 100), 2);

        $dt[$i]['penyusutan_perbulan'] = round($dt[$i]['penyusutan_pertahun'] / $dt[$i]['selisih'], 2);
        $dt[$i]['saldo_akhir'] = round(($dt[$i]['saldo_awal'] - $dt[$i]['penyusutan_pertahun']), 2);


        //data update
        $data_update["penyusutan_perbulan"] = round($dt[$i]['penyusutan_perbulan']);
    }

    $model = $sql->update("acc_asset", $data_update, array('id' => $params['id']));

    return successResponse($response, [
        'data_update' => $data_update,
        'list' => $dt,
        'base_url' => str_replace('api/', '', config('SITE_URL')),
    ]);
});
$app->get('/acc/m_asset/getAkun', function ($request, $response) {
    $params = $request->getParams();

    $sql = $this->db;
    $sql->select("*")->from("acc_m_akun")
            ->where("is_deleted", "=", 0)
            ->andWhere("is_tipe", "=", 0);
    $models = $sql->findAll();
    return successResponse($response, [
        'list' => $models,
        'base_url' => str_replace('api/', '', config('SITE_URL')),
    ]);
});


$app->get('/acc/m_asset/index', function ($request, $response) {
    $params = $request->getParams();
    // $sort     = "m_akun.kode ASC";
    $offset = isset($params['offset']) ? $params['offset'] : 0;
    $limit = isset($params['limit']) ? $params['limit'] : 20;

    $db = $this->db;
    $db->select("acc_asset.*,acc_m_lokasi.nama as nm_lokasi,acc_m_lokasi.kode as kode_lokasi, acc_umur_ekonomis.nama as nama_umur, acc_umur_ekonomis.tahun as tahun_umur, acc_umur_ekonomis.persentase as persentase_umur, akun_asset.nama as nm_akun_asset, akun_akumulasi.nama as nm_akun_akumulasi, akun_beban.nama as nm_akun_beban, akun_laba_rugi.nama as nm_akun_laba_rugi,akun_kas_pelepasan.nama as nm_akun_kas_pelepasan, acc_riw_penyusutan_dt.id as id_penyusutan")
            ->from("acc_asset")
            ->leftJoin("acc_m_lokasi", "acc_m_lokasi.id = acc_asset.lokasi_id")
            ->leftJoin("acc_umur_ekonomis", "acc_umur_ekonomis.id = acc_asset.umur_ekonomis")
            ->leftJoin("acc_m_akun akun_asset", "akun_asset.id = acc_asset.akun_asset_id")
            ->leftJoin("acc_m_akun akun_akumulasi", "akun_akumulasi.id = acc_asset.akun_akumulasi_id")
            ->leftJoin("acc_m_akun akun_beban", "akun_beban.id = acc_asset.akun_beban_id")
            ->leftJoin("acc_m_akun akun_laba_rugi", "akun_laba_rugi.id = acc_asset.akun_laba_rugi_id")
            ->leftJoin("acc_m_akun akun_kas_pelepasan", "akun_kas_pelepasan.id = acc_asset.akun_kas_pelepasan_id")
            ->leftJoin("acc_riw_penyusutan_dt", "acc_riw_penyusutan_dt.asset_id = acc_asset.id")
            ->groupBy("acc_asset.id")
            ->orderBy('acc_asset.id DESC');

    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);

        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("acc_asset.is_deleted", '=', $val);
            } else if ($key == 'nama') {
                $db->where("acc_asset.nama", 'LIKE', $val);
            } else {
                $db->where($key, 'LIKE', $val);
            }
        }
    }

    /** Set limit */
    if (isset($params['limit']) && !empty($params['limit'])) {
        $db->limit($params['limit']);
    }

    /** Set offset */
    if (isset($params['offset']) && !empty($params['offset'])) {
        $db->offset($params['offset']);
    }

    $models = $db->findAll();
    $totalItem = $db->count();

    //nilai buku terakhir
    $getPenyusutan = $db->select("sum(penyusutan_perbulan) as total_penyusutan,asset_id")
                    ->from("acc_riw_penyusutan_dt")
                    ->groupBy("asset_id")->findAll();
    $arr_penyusutan = [];
    foreach ($getPenyusutan as $key => $value) {
        $arr_penyusutan[$value->asset_id] = (int) $value->total_penyusutan;
    }

    foreach ($models as $key => $value) {
        if ($value->lokasi_id == -1) {
            $value->lokasi = ["id" => $value->lokasi_id, "nama" => 'Lainya'];
        } else {
            $value->lokasi = ["id" => $value->lokasi_id, "nama" => $value->nm_lokasi, "kode_lokasi" => $value->kode_lokasi];
        }
        $value->tanggal_beli_format = date("d-m-Y", strtotime($value->tanggal_beli));
        $value->umur = ["id" => $value->umur_ekonomis, "nama" => $value->nama_umur, "tahun" => $value->tahun_umur, "persentase" => $value->persentase_umur];
        $value->persentase = (Float) $value->persentase;
        $value->akun_asset = ["id" => $value->akun_asset_id, "nama" => $value->nm_akun_asset];
        $value->akun_akumulasi = ["id" => $value->akun_akumulasi_id, "nama" => $value->nm_akun_akumulasi];
        $value->akun_beban = ["id" => $value->akun_beban_id, "nama" => $value->nm_akun_beban];
        $value->akun_laba_rugi = ["id" => $value->akun_laba_rugi_id, "nama" => $value->nm_akun_laba_rugi];
        $value->akun_kas_pelepasan = ["id" => $value->akun_kas_pelepasan_id, "nama" => $value->nm_akun_kas_pelepasan];
        if (isset($value->id_penyusutan)) {
            $value->proses_penyusutan = 1;
        } else {
            $value->proses_penyusutan = 0;
        }

        if (isset($arr_penyusutan[$value->id])) {
            $value->nilai_buku_terakhir = $value->harga_beli - $arr_penyusutan[$value->id];
        } else {
            $value->nilai_buku_terakhir = 0;
        }
    }
//     print_r($models);exit();
//      print_r($arr);exit();
    return successResponse($response, [
        'list' => $models,
        'totalItems' => $totalItem,
        'base_url' => str_replace('api/', '', config('SITE_URL')),
    ]);
});

$app->get('/acc/m_asset/generateKode', function ($request, $response) {

    $sql = $this->db;
    $y = date("Y");
    $m = date("m");
    $cek = $sql->find("select kode from acc_asset where FROM_UNIXTIME(created_at,'%m') = " . $m . " and FROM_UNIXTIME(created_at,'%Y') = " . $y . " order by kode desc");
    $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -4)) + 1;
    $no_urut = substr('0000' . $urut, -4);
    $no_transaksi = "AST" . date("my") . $no_urut;

    return successResponse($response, $no_transaksi);
});
$app->post('/acc/m_asset/save', function ($request, $response) {

    $params = $request->getParams();
    $data = $params;
    $sql = $this->db;

    if ($data["is_penyusutan"] == 1) {
        $validasi = validasi($data, ["umur" => 'required', "persentase" => 'required', "nilai_residu" => 'required', 'akun_asset' => 'required', 'akun_akumulasi' => 'required', 'akun_beban' => 'required']);
    } else {
        $validasi = validasi($data);
    }

    if ($validasi === true) {
        $data["lokasi_id"] = $data["lokasi"]["id"];
        if ($data["lokasi"]["id"] == -1) {
            $data["nama_lokasi"] = $data["nama_lokasi"];
        } else {
            $data["nama_lokasi"] = $data["lokasi"]["nama"];
        }
        if ($data['is_penyusutan'] == 1) {
            $data["akun_asset_id"] = $data["akun_asset"]["id"];
            $data["akun_akumulasi_id"] = $data["akun_akumulasi"]["id"];
            $data["akun_beban_id"] = $data["akun_beban"]["id"];
        }

        $data["tanggal_beli"] = date("Y-m-d", strtotime($data["tanggal"]));
        $data["harga_beli"] = $data["harga"];
        if ($data["is_penyusutan"] == 1) {
            $data["umur_ekonomis"] = $data["umur"]["id"];
            $data["tahun"] = $data["umur"]["tahun"];
            $data["persentase"] = $data["umur"]["persentase"];
            $data["nilai_residu"] = $data["nilai_residu"];
            $data["tgl_mulai_penyusutan"] = date("Y-m-d", strtotime($data["tgl_mulai_penyusutan"]));
        }
        $data["status"] = "Aktif";

//         echo json_encode($data); die();
        if (isset($data["id"]) && !empty($data['id'])) {
            $model = $sql->update("acc_asset", $data, array('id' => $data['id']));
        } else {
            $model = $sql->insert("acc_asset", $data);
        }

        if ($model) {
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, ['Data Gagal Di Simpan']);
        }
    } else {
        return unprocessResponse($response, $validasi);
    }
});


$app->get('/acc/m_asset/get_min_tgl_pelepasan', function ($request, $response) {

    $sql = $this->db;
    $params = $request->getParams();
    $asset_id = $params["id"];
    $cek = $sql->select("acc_riw_penyusutan.periode")
            ->from("acc_riw_penyusutan_dt")
            ->leftJoin("acc_riw_penyusutan", "acc_riw_penyusutan.id = acc_riw_penyusutan_dt.riw_id")
            ->where("acc_riw_penyusutan_dt.asset_id", "=", $asset_id)
            ->orderBy("acc_riw_penyusutan.periode DESC")
            ->limit("1")
            ->find();
    if ($cek) {
        $min_tgl = date("Y-m-t", strtotime($cek->periode));
        $min_tgl = date("Y-m-d", strtotime($min_tgl . "+1 days"));
        $minimal = true;
    } else {
        $min_tgl = date("Y-m-d");
        $minimal = false;
    }

    return successResponse($response, ['tanggal' => $min_tgl, 'minimal' => $minimal]);
});

$app->post('/acc/m_asset/proses_pelepasan', function ($request, $response) {

    $params = $request->getParams();
    $data = $params;
    $sql = $this->db;
    if ($data["jenis_pelepasan"] == "Dijual") {
        $validasi = validasi_pelepasan($data, ["nilai_pelepasan" => 'required', 'akun_kas_pelepasan' => 'required', 'akun_laba_rugi' => 'required']);
    } else {
        $validasi = validasi_pelepasan($data, ['akun_laba_rugi' => 'required']);
    }


    if ($validasi === true) {
        $data["tgl_pelepasan"] = date("Y-m-d", strtotime($data["tgl_pelepasan"]));
        $data["status"] = $data["jenis_pelepasan"];
        if ($data["jenis_pelepasan"] == "Dijual") {
            $data["akun_kas_pelepasan_id"] = $data["akun_kas_pelepasan"]["id"];
        }
        $data["akun_laba_rugi_id"] = $data["akun_laba_rugi"]["id"];

        //update asset
        $model = $sql->update("acc_asset", $data, array('id' => $data['id']));

        //hitung akumulasi penyusutan sampai tgl pelepasan
        $datetime1 = new DateTime(date("Y-m-t", strtotime($data['tanggal_beli'])));
        $datetime2 = new DateTime(date("Y-m-d", strtotime($data['tgl_pelepasan'])));
        $interval = $datetime1->diff($datetime2);
        $selisih = $interval->m + 1 + ($interval->y * 12);
        $nilai_akumulasi = $data['penyusutan_perbulan'] * $selisih;

        //jurnal
        $cek = $sql->find("select no_transaksi from acc_jurnal order by no_transaksi desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_transaksi, -4)) + 1;
        $no_urut = substr('0000' . $urut, -4);
        $no_transaksi = $data["lokasi"]["kode_lokasi"] . date("y") . "JRNL" . $no_urut;
        $keterangan = "Pelepasan Asset " . $data["nama"];

        //insert jurnal & trans detail
        $dt_juranal = [];
        $total_kredit = 0;
        $total_debit = 0;
        if ($data['jenis_pelepasan'] == 'Dijual') {
            $hitung_laba_rugi = ($data["nilai_pelepasan"] + (int) ($nilai_akumulasi)) - $data["harga_beli"];
            if ($hitung_laba_rugi <= 0) {
                //rugi
                $total_kredit = $data["harga_beli"];
                $total_debit = $data["nilai_pelepasan"] + $nilai_akumulasi + abs($hitung_laba_rugi);
            } else {
                //laba
                $total_kredit = $data["harga_beli"] + $hitung_laba_rugi;
                $total_debit = $data["nilai_pelepasan"] + $nilai_akumulasi;
            }
        } else {
            $rugi = abs($data["harga_beli"] - $nilai_akumulasi);
            $total_kredit = $data["harga_beli"];
            $total_debit = $nilai_akumulasi + abs($rugi);
        }

        $d_jurnal = array(
            "no_transaksi" => $no_transaksi,
            "m_lokasi_id" => $data["lokasi"]["id"],
            "keterangan" => $keterangan,
            "tanggal" => $data["tgl_pelepasan"],
            "total_kredit" => $total_kredit,
            "total_debit" => $total_debit
        );
        $insert_jurnal = $sql->insert("acc_jurnal", $d_jurnal);

        if ($data['jenis_pelepasan'] == 'Dijual') {
            $hitung_laba_rugi = ($data["nilai_pelepasan"] + (int) ($nilai_akumulasi)) - $data["harga_beli"];
            if ($hitung_laba_rugi <= 0) {
                //rugi
                $dt_juranal["Kas Piutang"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_kas_pelepasan"]["id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => $data["nilai_pelepasan"],
                    "kredit" => 0,
                    "keterangan" => $keterangan . ' (Kas/Piutang)'
                );

                $dt_juranal["Akumulasi"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_akumulasi_id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => $nilai_akumulasi,
                    "kredit" => 0,
                    "keterangan" => $keterangan . ' (Akumulasi)'
                );

                $dt_juranal["Rugi"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_laba_rugi"]["id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => abs($hitung_laba_rugi),
                    "kredit" => 0,
                    "keterangan" => $keterangan . ' (Rugi)'
                );

                $dt_juranal["Akun Asset"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_asset_id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => 0,
                    "kredit" => $data["harga_beli"],
                    "keterangan" => $keterangan . ' (Akun Asset)'
                );
            } else {
                //laba
                $dt_juranal["Kas Piutang"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_kas_pelepasan"]["id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => $data["nilai_pelepasan"],
                    "kredit" => 0,
                    "keterangan" => $keterangan . ' (Kas/Piutang)'
                );

                $dt_juranal["Akumulasi"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_akumulasi_id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => $nilai_akumulasi,
                    "kredit" => 0,
                    "keterangan" => $keterangan . ' (Akumulasi)'
                );

                $dt_juranal["Laba"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_laba_rugi"]["id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => 0,
                    "kredit" => $hitung_laba_rugi,
                    "keterangan" => $keterangan . ' (Laba)'
                );

                $dt_juranal["Akun Asset"] = array(
                    "acc_jurnal_id" => $insert_jurnal->id,
                    "m_akun_id" => $data["akun_asset_id"],
                    "m_lokasi_id" => $data["lokasi"]["id"],
                    "debit" => 0,
                    "kredit" => $data["harga_beli"],
                    "keterangan" => $keterangan . ' (Akun Asset)'
                );
            }
        } else {
            $rugi = abs($data["harga_beli"] - $nilai_akumulasi);
            $total_kredit = $data["harga_beli"];
            $total_debit = $nilai_akumulasi + abs($rugi);

            $dt_juranal["Akumulasi"] = array(
                "acc_jurnal_id" => $insert_jurnal->id,
                "m_akun_id" => $data["akun_akumulasi_id"],
                "m_lokasi_id" => $data["lokasi"]["id"],
                "debit" => $nilai_akumulasi,
                "kredit" => 0,
                "keterangan" => $keterangan . ' (Akumulasi)'
            );

            $dt_juranal["Rugi"] = array(
                "acc_jurnal_id" => $insert_jurnal->id,
                "m_akun_id" => $data["akun_laba_rugi"]["id"],
                "m_lokasi_id" => $data["lokasi"]["id"],
                "debit" => abs($rugi),
                "kredit" => 0,
                "keterangan" => $keterangan . ' (Rugi)'
            );

            $dt_juranal["Akun Asset"] = array(
                "acc_jurnal_id" => $insert_jurnal->id,
                "m_akun_id" => $data["akun_asset_id"],
                "m_lokasi_id" => $data["lokasi"]["id"],
                "debit" => 0,
                "kredit" => $data["harga_beli"],
                "keterangan" => $keterangan . ' (Akun Asset)'
            );
        }

        foreach ($dt_juranal as $key => $value) {
            //insert dt_jurnal                
            $insert_dt_jurnal = $sql->insert("acc_jurnal_det", $dt_juranal[$key]);
            //insert transdt
            $dt_juranal[$key]["reff_id"] = $insert_jurnal->id;
            $dt_juranal[$key]["reff_type"] = "acc_jurnal_pelepasan";
            $dt_juranal[$key]["tanggal"] = $data["tgl_pelepasan"];
            $dt_juranal[$key]["kode"] = $no_transaksi;
            $insert_trans = $sql->insert("acc_trans_detail", $dt_juranal[$key]);
        }

        if ($model) {
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, ['Data Gagal Di Simpan']);
        }
    } else {
        return unprocessResponse($response, $validasi);
    }
});

$app->post('/acc/m_asset/trash', function ($request, $response) {

    $data = $request->getParams();
    $db = $this->db;

//    $cek_komponenGaji = $db->select('*')
    //    ->from('m_komponen_gaji')
    //    ->where('m_akun_id','=',$data['id'])
    //    ->find();
    //
    //    if (!empty($cek_komponenGaji)) {
    //       return unprocessResponse($response, ['Data Akun Masih Di Gunakan Pada Master Komponen Gaji']);
    //    }
//    $cek_Gaji = $db->select('*')
    //    ->from('t_penggajian')
    //    ->where('m_akun_id','=',$data['id'])
    //    ->find();
    //
    //    if (!empty($cek_Gaji)) {
    //       return unprocessResponse($response, ['Data Akun Masih Di Gunakan Pada Transaksi Penggajian']);
    //    }

    $model = $db->update("acc_asset", $data, array('id' => $data['id']));
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});

$app->post('/acc/m_asset/delete', function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;


    $delete = $db->delete('m_asset', array('id' => $data['id']));
    if ($delete) {
        return successResponse($response, ['data berhasil dihapus']);
    } else {
        return unprocessResponse($response, ['data gagal dihapus']);
    }
});
