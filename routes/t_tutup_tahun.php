<?php

function validasi($data, $custom = array()) {
    $validasi = array(
//        'no_transaksi' => 'required',
//        'm_lokasi_id' => 'required',
//        'm_akun_id' => 'required',
//        'tanggal' => 'required',
//        'total' => 'required',
//        'dibayar_kepada' => 'required'
    );
//    GUMP::set_field_name("parent_id", "Akun");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->get('/acc/t_tutup_tahun/index', function ($request, $response) {
    $params = $request->getParams();
    $tableuser = tableUser();
    $sort = "id DESC";
    $offset = isset($params['offset']) ? $params['offset'] : 0;
    $limit = isset($params['limit']) ? $params['limit'] : 10;

    $db = $this->db;

    /** Select Gudang from database */
    $db->select("
      acc_tutup_buku.*,
      " . $tableuser . ".nama as namaUser
      ")
            ->from("acc_tutup_buku")
            ->leftJoin($tableuser, $tableuser . ".id=acc_tutup_buku.created_by")
            ->where("acc_tutup_buku.jenis", "=", "tahun");
    /** Add filter */
    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);
        foreach ($filter as $key => $val) {
            if ($key == "tahun") {
                if ($val != "semua") {
                    $db->where($key, '=', $val);
                }
            } else {
                $db->where($key, 'LIKE', $val);
            }
        }
    }

    /** Set limit */
    if (!empty($limit)) {
        $db->limit($limit);
    }

    /** Set offset */
    if (!empty($offset)) {
        $db->offset($offset);
    }

    /** Set sorting */
    if (!empty($params['sort'])) {
        $db->sort($sort);
    }

    $models = $db->findAll();
    $totalItem = $db->count();
    // print_r($models);exit();
    $array = [];
    foreach ($models as $key => $val) {
        $akun_ikhtisar = $db->find("select * from acc_m_akun where id ={$val->akun_ikhtisar_id}");
        $akun_pemindaian = $db->find("select * from acc_m_akun where id ={$val->akun_pemindahan_modal_id}");
        $tgl = date('Y-m-d', strtotime($val->tahun . '-' . $val->bulan . '-01'));
        $array[$key] = (array) $val;
        $array[$key]['akun_ikhtisar_id'] = $akun_ikhtisar;
        $array[$key]['akun_pemindahan_modal_id'] = $akun_pemindaian;
        $array[$key]['hasil_rp'] = rp($val->hasil_lr);
        $array[$key]['bln_tahun'] = $tgl;
        $array[$key]['created_at'] = date("d-m-Y", $val->created_at);
    }


    return successResponse($response, ['list' => $array, 'totalItems' => $totalItem]);
});


$app->get('/acc/t_tutup_tahun/tahun', function ($request, $response) {
    $db = $this->db;
    $list = $db->findAll("select * from acc_tutup_buku");
    $list_tahun = [];
    foreach ($list as $val) {
        $list_tahun[] = $val->tahun;
    }

    $tahun = range(date("Y") - 3, date("Y") + 1);

    $listtahun = array_unique(array_merge($list_tahun, $tahun));

    return successResponse($response, $tahun);
});


$app->get('/acc/t_tutup_tahun/getDetail', function ($request, $response) {
    $params = $request->getParams();
//    print_r($params);die();

    $db = $this->db;

    /*
     * deklarasi 2 tipe
     */
    $tipe = [
        [
            "nama" => "Pendapatan",
            "nama_akun" => [
                4, 8
            ]
        ],
        [
            "nama" => "Beban",
            "nama_akun" => [
                5, 6, 7, 9
            ]
        ]
    ];

//    print_r($tipe);die();

    $models = [];
    $sumdebit = 0;
    $sumkredit = 0;
    foreach ($tipe as $key => $val) {

        $models[$key]['nama'] = $val['nama'];
        $models[$key]['labarugi'] = 0;

        $index = 0;

        foreach ($val['nama_akun'] as $keys => $vals) {
            $akun = $db->select("*")->from("acc_m_akun")->where("parent_id", "=", $vals)->findAll();
            foreach ($akun as $keydetail => $valdetail) {
                $transdetail = $db->select("SUM(debit) as debit, SUM(kredit) as kredit")
                        ->from("acc_trans_detail")
                        ->where("m_akun_id", "=", $valdetail->id)
                        ->find();
                $models[$key]['detail'][$index]['nama'] = $valdetail->nama;
                if ($val['nama'] == "Pendapatan") {
                    $models[$key]['detail'][$index]['debit'] = ($transdetail->debit - $transdetail->kredit) != 0 ? $transdetail->debit - $transdetail->kredit : 0;
                    $models[$key]['detail'][$index]['kredit'] = 0;
                    $models[$key]['labarugi'] += $models[$key]['detail'][$index]['debit'];
                } else {
                    $models[$key]['detail'][$index]['debit'] = 0;
                    $models[$key]['detail'][$index]['kredit'] = ($transdetail->debit - $transdetail->kredit) != 0 ? $transdetail->debit - $transdetail->kredit : 0;
                    $models[$key]['labarugi'] += $models[$key]['detail'][$index]['kredit'];
                }

                $index++;
            }
        }
    }

//    print_r($models);
//    die();
    $tahun = date("Y", strtotime($params['tahun']));
    $labarugi = getLabaRugi($tahun . "-01-01", $tahun . "-12-31");
    
    $pendapatan = isset($labarugi['total']['PENDAPATAN']) ? $labarugi['total']['PENDAPATAN'] : 0;
    $biaya = isset($labarugi['total']['BIAYA']) ? $labarugi['total']['BIAYA'] : 0;
    $beban = isset($labarugi['total']['BEBAN']) ? $labarugi['total']['BEBAN'] : 0;
    $totallabarugi = $pendapatan - $biaya - $beban;

    $data['labarugimodal'] = $totallabarugi;
    $data['labarugimodal2'] = $models[1]['labarugi'] - $models[0]['labarugi'];

//    print_r($data);
//    die();

    return successResponse($response, [
        'detail' => $models,
        'data' => $data,
        'total_kredit' => $sumkredit,
    ]);
});



$app->post('/acc/t_tutup_tahun/save', function ($request, $response) {

    $params = $request->getParams();
    $data = $params;

    $db = $this->db;
    $validasi = validasi($data);
    if ($validasi === true) {
        $cekData = $db->select("*")->from("acc_tutup_buku")
                ->where("jenis", "=", "tahun")
                ->where("tahun", "=", $data['tahun'])
                ->count();
        if ($cekData > 0) {
            return unprocessResponse($response, 'Data Sudah Ada');
            die();
        }

        /*
         * insert acc_tutup_buku
         */
        $data['jenis'] = "tahun";
        $data['akun_ikhtisar_id'] = $data['akun_ikhtisar_id']['id'];
        $data['akun_pemindahan_modal_id'] = $data['akun_pemindahan_modal_id']['id'];

//        print_r($data['form']);die();
        $model = $db->insert("acc_tutup_buku", $data);

        if ($model) {
            /*
             * update m_setting tanggalnya
             */
            $tanggal = $data['tahun'] + 1 . "-01-01";
            $db->update("acc_m_setting", ["tanggal" => $tanggal], 1);

            /*
             * akun ikhtisar
             */
            $det['acc_tutup_buku_id'] = $model->id;
            $det['m_akun_id'] = $data['akun_ikhtisar_id'];
            $det['kredit'] = $data['kredit'];

            $insertdetail = $db->insert("acc_tutup_buku_det", $det);

            $transdet[0]['m_akun_id'] = $data['akun_ikhtisar_id'];
            $transdet[0]['tanggal'] = date("Y-m-d");
            $transdet[0]['kredit'] = $data['kredit'];
            $transdet[0]['reff_type'] = "acc_tutup_buku_det";
            $transdet[0]['reff_id'] = $insertdetail->id;


            /*
             * akun pemindahan modal
             */
            $det2['acc_tutup_buku_id'] = $model->id;
            $det2['m_akun_id'] = $data['akun_pemindahan_modal_id'];
            $det2['debit'] = $data['debit'];

            $insertdetail = $db->insert("acc_tutup_buku_det", $det2);

            $transdet[1]['m_akun_id'] = $data['akun_pemindahan_modal_id'];
            $transdet[1]['tanggal'] = date("Y-m-d");
            $transdet[1]['debit'] = $data['debit'];
            $transdet[1]['reff_type'] = "acc_tutup_buku_det";
            $transdet[1]['reff_id'] = $insertdetail->id;

            insertTransDetail($transdet);

            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, 'Data Gagal Di Simpan');
        }
    } else {
        return unprocessResponse($response, $validasi);
    }
});

$app->post('/acc/t_tutup_tahun/trash', function ($request, $response) {

    $data = $request->getParams();
    $db = $this->db;

    $model = $db->update("t_tutup_buku", $data, array('id' => $data['id']));
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});
