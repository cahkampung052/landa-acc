<?php

date_default_timezone_set('Asia/Jakarta');

function validasi($data, $custom = array()) {
    $validasi = array(
        'customer' => 'required',
        'jatuh_tempo' => 'required',
        'akun_debit' => 'required',
        'akun_piutang' => 'required',
        'tanggal' => 'required',
        'total' => 'required',
    );
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->get('/acc/t_saldo_awal_piutang_percustomer/index', function ($request, $response) {
    $params = $request->getParams();
    $sort = "acc_saldo_piutang.created_at DESC";
    $offset = isset($params['offset']) ? $params['offset'] : 0;
    $limit = isset($params['limit']) ? $params['limit'] : 20;

    $db = $this->db;
    $db->select("
            acc_saldo_piutang.*,
            acc_m_kontak.kode as kodeCus,
            acc_m_kontak.nama as namaCus,
            acc_m_kontak.tlp as tlpCus,
            acc_m_kontak.email as emailCus,
            acc_m_kontak.alamat as alamatCus,
            debit.kode as kodeAkun,
            debit.nama as namaAkun,
            piutang.kode as kodeAkunPiutang,
            piutang.nama as namaAkunPiutang,
            acc_m_lokasi.kode as kodeLokasi,
            acc_m_lokasi.nama as namaLokasi
        ")
            ->from('acc_saldo_piutang')
            ->leftJoin('acc_m_kontak', 'acc_m_kontak.id = acc_saldo_piutang.m_kontak_id')
            ->leftJoin('acc_m_akun debit', 'debit.id = acc_saldo_piutang.m_akun_id')
            ->leftJoin('acc_m_akun piutang', 'piutang.id = acc_saldo_piutang.m_akun_piutang_id')
            ->leftJoin('acc_m_lokasi', 'acc_m_lokasi.id = acc_saldo_piutang.m_lokasi_id')
            ->orderBy($sort);
//        ->where("acc_pemasukan.is_deleted", "=", 0);

    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);

        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("acc_saldo_piutang.is_deleted", '=', $val);
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

    if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
        $db->where("acc_saldo_piutang.m_lokasi_id", "=", $params['m_lokasi_id']);
    }

    $models = $db->findAll();
    $totalItem = $db->count();

    foreach ($models as $key => $val) {
        $val->customer = ['nama' => $val->namaCus, 'id' => $val->m_kontak_id, 'kode' => $val->kodeCus, 'tlp' => $val->tlpCus, 'email' => $val->emailCus, 'alamat' => $val->alamatCus];
        $val->akun_debit = ['nama' => $val->namaAkun, 'id' => $val->m_akun_id, 'kode' => $val->kodeAkun];
        $val->akun_piutang = ['nama' => $val->namaAkunPiutang, 'id' => $val->m_akun_piutang_id, 'kode' => $val->kodeAkunPiutang];
        $val->lokasi = ['nama' => $val->namaLokasi, 'id' => $val->m_lokasi_id, 'kode' => $val->kodeLokasi];
        $val->tanggal_formated = date("d-m-Y", strtotime($val->tanggal));
        $val->jatuh_tempo_formated = date("d-m-Y", strtotime($val->jatuh_tempo));
        $val->status = ucfirst($val->status);
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


$app->post('/acc/t_saldo_awal_piutang_percustomer/save', function ($request, $response) {

    $params = $request->getParams();
    $data = $params;

//    print_r($data);
//    die();
    $sql = $this->db;
    $validasi = validasi($data);
    if ($validasi === true) {
        /*
         * kode
         */
        $kode = generateNoTransaksi("saldo_piutang", 0);
//        print_r($kode);die();


        $insert['m_kontak_id'] = $data['customer']['id'];
        $insert['tanggal'] = date("Y-m-d", strtotime($data['tanggal']));
        $insert['m_lokasi_id'] = $data['lokasi']['id'];
        $insert['total'] = $data['total'];
        $insert['jatuh_tempo'] = date("Y-m-d", strtotime($data['tanggal']));
        $insert['status_piutang'] = 'belum lunas';
        $insert['status'] = $data['status'];
        $insert['m_akun_id'] = $data['akun_debit']['id'];
        $insert['m_akun_piutang_id'] = $data['akun_piutang']['id'];
        $insert['no_invoice'] = isset($data['no_invoice']) && !empty($data['no_invoice']) ? $data['no_invoice'] : NULL;
        $insert['keterangan'] = (isset($data['keterangan']) && !empty($data['keterangan']) ? $data['keterangan'] : '');

//        print_r($insert);die;
        if (isset($data['id']) && !empty($data['id'])) {
            $insert['kode'] = $data['kode'];
            $model = $sql->update("acc_saldo_piutang", $insert, ["id" => $data['id']]);
        } else {
            $insert['kode'] = $kode;
            $model = $sql->insert("acc_saldo_piutang", $insert);
        }

        //delete transdetail
        $deletetransdetail = $sql->delete("acc_trans_detail", ["reff_id" => $model->id, "reff_type" => "acc_saldo_piutang"]);

        /*
         * deklarasi untuk simpan ke transdetail
         */
        $index = 0;
        $transDetail = [];

        $transDetail[0]['m_lokasi_id'] = $data['lokasi']['id'];
        $transDetail[0]['m_akun_id'] = $data['akun_debit']['id'];
        $transDetail[0]['tanggal'] = date("Y-m-d", strtotime($data['tanggal']));
        $transDetail[0]['debit'] = $data['total'];
        $transDetail[0]['reff_type'] = "acc_saldo_piutang";
        $transDetail[0]['reff_id'] = $model->id;
        $transDetail[0]['kode'] = $model->kode;
        $transDetail[0]['keterangan'] = (isset($data['keterangan']) && !empty($data['keterangan']) ? $data['keterangan'] : '');

        $transDetail[1]['m_lokasi_id'] = $data['lokasi']['id'];
        $transDetail[1]['m_akun_id'] = $data['akun_piutang']['id'];
        $transDetail[1]['tanggal'] = date("Y-m-d", strtotime($data['tanggal']));
        $transDetail[1]['kredit'] = $data['total'];
        $transDetail[1]['reff_type'] = "acc_saldo_piutang";
        $transDetail[1]['reff_id'] = $model->id;
        $transDetail[1]['keterangan'] = (isset($data['keterangan']) && !empty($data['keterangan']) ? $data['keterangan'] : '');
        $transDetail[1]['kode'] = $model->kode;



        /*
         * Simpan array trans detail ke database jika simpan dan kunci
         */
        if ($data['status'] == "terposting") {
            insertTransDetail($transDetail);
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


$app->post('/acc/t_saldo_awal_piutang_percustomer/delete', function ($request, $response) {

    $data = $request->getParams();
    $db = $this->db;


    $model = $db->delete("acc_saldo_piutang", ['id' => $data['id']]);
    $model2 = $db->delete("acc_trans_detail", ["reff_type" => "acc_saldo_piutang", "reff_id" => $data['id']]);
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});
