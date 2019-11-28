<?php

date_default_timezone_set('Asia/Jakarta');

function validasi($data, $custom = array()) {
    $validasi = array(
//        'kode'          => 'required',
        'tanggal' => 'required',
        'akun' => 'required',
        'lokasi' => 'required',
        // 'm_akun_denda_id' => 'required',
        // 'm_unker_id'   => 'required',
        'total' => 'required',
        'supplier' => 'required',
    );

    $cek = validate($data, $validasi, $custom);
    return $cek;
}

/**
 * get view
 */
$app->get('/acc/t_pembayaran_hutang/view', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;

    $db->select("
            acc_bayar_hutang_det.*,
            acc_saldo_hutang.*,
            acc_m_akun.nama as namaAkun,
            acc_m_akun.kode as kodeAkun,
            acc_bayar_hutang_det.created_at,
            acc_bayar_hutang_det.created_by
        ")
            ->from("acc_bayar_hutang_det")
            ->leftJoin("acc_saldo_hutang", "acc_saldo_hutang.id= acc_bayar_hutang_det.acc_saldo_hutang_id")
            ->leftJoin("acc_m_akun", "acc_m_akun.id = acc_saldo_hutang.m_akun_hutang_id")
            ->where("acc_bayar_hutang_det.acc_bayar_hutang_id", "=", $params['id']);

    $models = $db->findAll();
    foreach ($models as $val) {
        $val->bayar = $val->bayar - $val->potongan;
        $val->akun_hutang = ["id" => $val->m_akun_hutang_id, "nama" => $val->namaAkun, "kode" => $val->kodeAkun];
    }

    $db->select("acc_m_akun.*")
            ->from("acc_bayar_hutang")
            ->leftJoin("acc_m_akun", "acc_m_akun.id = acc_bayar_hutang.m_akun_denda_id")
            ->where("acc_bayar_hutang.id", "=", $params['id']);
    $akun_denda = $db->find();

    return successResponse($response, ['detail' => $models, 'akun_denda' => $akun_denda]);
});

/*
 * get jurnal
 */
$app->get('/acc/t_pembayaran_hutang/getJurnal', function ($request, $response) {
    $param = $request->getParams();
    $db = $this->db;

    if (isset($param['id']) && !empty($param['id'])) {
        $db->select("acc_trans_detail.*, acc_m_akun.nama as namaAkun, acc_m_akun.kode as kodeAkun")
                ->from("acc_trans_detail")
                ->leftJoin("acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
                ->orderBy("tanggal, acc_trans_detail.id ASC")
                ->where('reff_type', '=', 'acc_bayar_hutang')->where('reff_id', '=', $param['id']);
        $model = $db->findAll();

        $debit = $kredit = 0;
        foreach ($model as $key => $val) {

            if ($val->debit != 0) {
                $val->tipe = "debit";
            } else {
                $val->tipe = "kredit";
            }

            $val->akun = [
                'id' => $val->m_akun_id,
                'kode' => $val->kodeAkun,
                'nama' => $val->namaAkun,
            ];


            $debit += $val->debit;
            $kredit += $val->kredit;
        }

        $total['totalDebit'] = $debit;
        $total['totalKredit'] = $kredit;

        return successResponse($response, ["total" => $total, "detail" => $model]);
    }

    return unprocessResponse($response, ["total" => [], "detail" => []]);
});

/** Ambil data supplier yang belum dihapus */
$app->get('/acc/t_pembayaran_hutang/getSupplier', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    $search = $params['val'];
    $models = $db->select("*")
            ->from("m_supplier")
            ->where("is_deleted", "=", 0)
            ->customWhere('nama LIKE "%' . $search . '%"', 'AND')
            ->limit(50)
            ->findAll();

    return successResponse($response, ['list' => $models]);
});


/**
 * get list hutang
 */
$app->get('/acc/t_pembayaran_hutang/getListHutang', function ($request, $response) {
    $param = $request->getParams();
    $db = $this->db;
    // $jatuh_tempo = date("Y-m-d", strtotime($param['jatuh_tempo']));

    $db->select("acc_saldo_hutang.*, acc_m_akun.nama as namaAkun, acc_m_akun.kode as kodeAkun")
            ->from("acc_saldo_hutang")
            ->join("join", "acc_m_akun", "acc_m_akun.id = acc_saldo_hutang.m_akun_hutang_id")
            ->where("acc_saldo_hutang.m_kontak_id", "=", $param['supplier_id'])
            ->where("acc_saldo_hutang.m_lokasi_id", "=", $param['lokasi_id'])
            ->andWhere("acc_saldo_hutang.status", "=", "terposting")
            ->andWhere("acc_saldo_hutang.status_hutang", "=", "belum lunas");
//    if (isset($param['tgl_verifikasi'])) {
//        $tgl = json_decode($param['tgl_verifikasi'], true);
//        $tgl_mulai = date('Y-m-d', strtotime($tgl['startDate']));
//        $tgl_selesai = date('Y-m-d', strtotime($tgl['endDate']));
//
//        $db->andWhere("inv_stok_masuk.tgl_monitoring_tt", ">=", $tgl_mulai);
//        $db->andWhere("inv_stok_masuk.tgl_monitoring_tt", "<=", $tgl_selesai);
//    }
    $model = $db->findAll();

    foreach ($model as $val) {
        $db->select("sum(acc_bayar_hutang_det.bayar) as bayar")
                ->from("acc_bayar_hutang_det")
                ->leftJoin("acc_saldo_hutang", "acc_saldo_hutang.id = acc_bayar_hutang_det.acc_saldo_hutang_id")
                ->where("acc_bayar_hutang_det.status", "=", "terposting")
                ->andWhere("acc_bayar_hutang_det.acc_saldo_hutang_id", "=", $val->id)
                ->groupBy("acc_bayar_hutang_det.acc_saldo_hutang_id");
        $bayar = $db->find();

        $val->sisa = $val->total - (isset($bayar->bayar) ? $bayar->bayar : 0);
        $val->akun_hutang = ["id" => $val->m_akun_hutang_id, "nama" => $val->namaAkun, "kode" => $val->kodeAkun];
    }

    return successResponse($response, $model);
});

$app->get('/acc/t_pembayaran_hutang/index', function ($request, $response) {
    $params = $request->getParams();
    $sort = "acc_bayar_hutang.tanggal DESC";
    $offset = isset($params['offset']) ? $params['offset'] : 0;
    $limit = isset($params['limit']) ? $params['limit'] : 20;

    $db = $this->db;
    $db->select("
            acc_bayar_hutang.*,
            acc_m_kontak.nama as namaSup,
            acc_m_kontak.kode as kodeSup,
            acc_m_user.nama as namaUser,
            kas.nama as namaKas,
            kas.kode as kodeKas,
            denda.nama as namaDenda,
            denda.kode as kodeDenda,
            acc_m_lokasi.nama as namaLokasi,
            acc_m_lokasi.kode as kodeLokasi
        ")
            ->from('acc_bayar_hutang')
            ->join('left join', 'acc_m_kontak', 'acc_m_kontak.id = acc_bayar_hutang.m_kontak_id')
            ->join('left join', 'acc_m_user', 'acc_m_user.id = acc_bayar_hutang.created_by')
            ->join('left join', 'acc_m_akun kas', 'kas.id = acc_bayar_hutang.m_akun_id')
            ->join('left join', 'acc_m_akun denda', 'denda.id = acc_bayar_hutang.m_akun_denda_id')
            ->join('left join', 'acc_m_lokasi', 'acc_m_lokasi.id = acc_bayar_hutang.m_lokasi_id')
            ->orderBy($sort);
//        ->where("acc_pemasukan.is_deleted", "=", 0);

    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);

        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("acc_kasbon.is_deleted", '=', $val);
            } else {
                $db->where($key, 'LIKE', $val);
            }
        }
    }

    /** Set supplier */
    if (isset($params['m_supplier_id']) && !empty($params['m_supplier_id'])) {
        $db->where("acc_m_kontak.id", "=", $params['m_supplier_id']);
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

    foreach ($models as $val) {
        $val->supplier = ['id' => $val->m_kontak_id, 'nama' => $val->namaSup, 'kode' => $val->kodeSup];
        $val->akun = ['id' => $val->m_akun_id, 'nama' => $val->namaKas, 'kode' => $val->kodeKas];
        $val->akun_denda = ['id' => $val->m_akun_denda_id, 'nama' => $val->namaDenda, 'kode' => $val->kodeDenda];
        $val->lokasi = ['id' => $val->m_lokasi_id, 'nama' => $val->namaLokasi, 'kode' => $val->kodeLokasi];
        $val->status = ucfirst($val->status);
        $val->tanggal_formated = date("d-m-Y", strtotime($val->tanggal));
        $val->created_at = date("d-m-Y H:i", $val->created_at);
    }


//     print_r($models);exit();
//    die();
//      print_r($arr);exit();
    return successResponse($response, [
        'list' => $models,
        'totalItems' => $totalItem,
        'base_url' => str_replace('api/', '', config('SITE_URL'))
    ]);
});


$app->post('/acc/t_pembayaran_hutang/save', function ($request, $response) {

    $params = $request->getParams();
    $data = $params;
    $sql = $this->db;
    $validasi = validasi($data['form']);
    if ($validasi === true) {
//        print_r($data);die;

        /*
         * kode
         */
        $kode = generateNoTransaksi("pembayaran_hutang", 0);


        $insert['m_kontak_id'] = $data['form']['supplier']['id'];
        $insert['m_lokasi_id'] = $data['form']['lokasi']['id'];
        $insert['m_akun_id'] = $data['form']['akun']['id'];
        $insert['tanggal'] = date("Y-m-d h:i:s", strtotime($data['form']['tanggal']));
        $insert['total'] = $data['form']['total'];
        $insert['status'] = $data['form']['status'];
        $insert['keterangan'] = (isset($data['form']['keterangan']) && !empty($data['form']['keterangan']) ? $data['form']['keterangan'] : '');
        $insert['tgl_mulai'] = isset($data['form']['startDate']) ? $data['form']['startDate'] : null;
        $insert['tgl_selesai'] = isset($data['form']['endDate']) ? $data['form']['endDate'] : null;
//        print_r($insert);die();
        if (isset($data['form']['id']) && !empty($data['form']['id'])) {
            $insert['kode'] = $data['form']['kode'];
            $model = $sql->update("acc_bayar_hutang", $insert, ["id" => $data['form']['id']]);
        } else {
            $insert['kode'] = $kode;
            $model = $sql->insert("acc_bayar_hutang", $insert);
        }

        /*
         * delete detail
         */
        $deletedetail = $sql->delete("acc_bayar_hutang_det", ["acc_bayar_hutang_id" => $model->id]);
        $deletetransdetail = $sql->delete("acc_trans_detail", ["reff_id" => $model->id, "reff_type" => "acc_bayar_hutang"]);

        $potongan = 0;
        if (!empty($params['detail'])) {
            foreach ($params['detail'] as $val) {

                $detail['kode'] = $model->kode;
                $detail['acc_bayar_hutang_id'] = $model->id;
                $detail['acc_saldo_hutang_id'] = $val['id'];
                $detail['tanggal'] = strtotime($model->tanggal);
                $detail['m_akun_id'] = $model->m_akun_id;
                $detail['sisa'] = $val['sisa'];
                $detail['bayar'] = $val['bayar'] + $val['potongan'];
                $detail['potongan'] = $val['potongan'];
                $detail['status'] = $model->status;
                $detail['created_at'] = $model->created_at;

                $potongan += $detail['potongan'];

                $sql->insert("acc_bayar_hutang_det", $detail);

                if (($val['sisa'] - $val['bayar'] - $val['potongan'] <= 0) && $model->status == "terposting") {
                    $sql->update("acc_saldo_hutang", ["status_hutang" => "lunas"], ["id" => $val['id']]);
                }
            }
        }

        /*
         * deklarasi untuk simpan ke transdetail
         */
        foreach ($data['jurnal'] as $key => $val) {
            $data['jurnal'][$key]['m_akun_id'] = $val['akun']['id'];
            $data['jurnal'][$key]['kode'] = $model->kode;
            $data['jurnal'][$key]['tanggal'] = date("Y-m-d", strtotime($data['form']['tanggal']));
            $data['jurnal'][$key]['reff_type'] = "acc_bayar_hutang";
            $data['jurnal'][$key]['reff_id'] = $model->id;
            $data['jurnal'][$key]['m_kontak_id'] = $model->m_kontak_id;
            $data['jurnal'][$key]['m_lokasi_id'] = $model->m_lokasi_id;
        }

//        print_r($data['jurnal']);die();
        /*
         * Simpan array trans detail ke database jika simpan dan kunci
         */
        if ($data['form']['status'] == "terposting") {
            insertTransDetail($data['jurnal']);
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

$app->post('/acc/t_pembayaran_hutang/saveJurnal', function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
//    print_r($data);die();
    $model = $db->find("select * from acc_bayar_hutang where id = '" . $data['id'] . "'");
    if (isset($model->id)) {
        $db->delete("acc_trans_detail", ["reff_type" => "acc_bayar_hutang", "reff_id" => $model->id]);
        if (isset($data['detail']) && !empty($data['detail'])) {
            $transDetail = [];
            foreach ($data['detail'] as $key => $val) {
                $m_akun_id = isset($val['akun']['id']) ? $val['akun']['id'] : 0;
                $transDetail[$key]['m_kontak_id'] = $val['m_kontak_id'];
                $transDetail[$key]['m_akun_id'] = $val['akun']['id'];
                $transDetail[$key]['tanggal'] = date("Y-m-d", strtotime($val['tanggal']));
                $transDetail[$key]['debit'] = $val['debit'];
                $transDetail[$key]['kredit'] = $val['kredit'];
                $transDetail[$key]['reff_type'] = "acc_bayar_hutang";
                $transDetail[$key]['reff_id'] = $model->id;
                $transDetail[$key]['kode'] = $model->kode;
                $transDetail[$key]['keterangan'] = 'Pembayaran Hutang (' . $model->kode . ')';
            }
            insertTransDetail($transDetail);
        }
    }

    return successResponse($response, []);
});

$app->post('/acc/t_pembayaran_hutang/delete', function ($request, $response) {

    $data = $request->getParams();
    $db = $this->db;


    $model = $db->delete("acc_bayar_hutang", ['id' => $data['id']]);
    $model = $db->delete("acc_bayar_hutang_det", ['acc_bayar_hutang_id' => $data['id']]);
    $model = $db->delete("acc_trans_detail", ["reff_type" => "acc_bayar_hutang", "reff_id" => $data['id']]);
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});

$app->get('/acc/t_pembayaran_hutang/print', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;

    $data = $db->select("acc_bayar_hutang.*, acc_m_lokasi.nama as namaLokasi, acc_m_kontak.nama as namaKontak, acc_m_akun.nama as namaAkun")
                    ->from("acc_bayar_hutang")
                    ->join("JOIN", "acc_m_lokasi", "acc_m_lokasi.id = acc_bayar_hutang.m_lokasi_id")
                    ->join("JOIN", "acc_m_kontak", "acc_m_kontak.id = acc_bayar_hutang.m_kontak_id")
                    ->join("JOIN", "acc_m_akun", "acc_m_akun.id = acc_bayar_hutang.m_akun_id")
                    ->where("acc_bayar_hutang.id", "=", $params['id'])->find();
    $arr = $db->select("acc_bayar_hutang_det.*, acc_saldo_hutang.kode as kodeHutang")
            ->from("acc_bayar_hutang_det")
            ->join("JOIN", "acc_saldo_hutang", "acc_saldo_hutang.id = acc_bayar_hutang_det.acc_saldo_hutang_id")
            ->where("acc_bayar_hutang_id", "=", $data->id)
            ->where("bayar", ">", 0)
            ->findAll();


    $data->sisa_bayar = 0;
    $data->invoice = [];
    foreach ($arr as $key => $val) {
        $val->sisa_bayar = $val->sisa - $val->bayar;
        $data->sisa_bayar += intval($val->sisa_bayar);
        $data->invoice[] = $val->kodeHutang;
    }
    
    $data->invoice = implode(", ", $data->invoice);
    $data->user = $_SESSION['user']['nama'];
    $data->terbilang = terbilang($data->total);

//    echo "<pre>", print_r($data), "</pre>";
//    echo "<pre>", print_r($arr), "</pre>";
//    die;



    $view = twigViewPath();
    if ($params['tipe'] == "voucher") {
        $content = $view->fetch('laporan/voucherHutang.html', [
            "data" => $data,
            "detail" => $arr,
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
    } else {
        $content = $view->fetch('laporan/kwitansiHutang.html', [
            "data" => $data,
            "detail" => $arr,
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
    }

    echo $content;
    echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
});

