<?php

function validasi($data, $custom = array()) {
    $validasi = array(
        'm_lokasi_asal_id' => 'required',
        'm_lokasi_tujuan_id' => 'required',
        'total' => 'required',
        'tanggal' => 'required',
        'm_akun_asal_id' => 'required',
        'm_akun_tujuan_id' => 'required',
    );
    GUMP::set_field_name("m_lokasi_asal_id", "Lokasi Asal");
    GUMP::set_field_name("m_lokasi_tujuan_id", "Lokasi Tujuan");
    GUMP::set_field_name("m_akun_asal_id", "Akun Asal");
    GUMP::set_field_name("m_akun_tujuan_id", "Akun Tujuan");
    GUMP::set_field_name("total", "Nominal");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->get('/acc/t_transfer/kode/{kode}', function ($request, $response) {
    $kode_unit_1 = $request->getAttribute('kode');
    $db = $this->db;
    $model = $db->find("select * from acc_transfer order by id desc");
    $urut = (empty($model)) ? 1 : ((int) substr($model->no_urut, -3)) + 1;
    $no_urut = substr('0000' . $urut, -3);
    return successResponse($response, ["kode" => $kode_unit_1 . "TRN" . date("y") . $no_urut, "urutan" => $no_urut]);
});
$app->get('/acc/t_transfer/akunKas', function ($request, $response) {
    $db = $this->db;
    $models = $db->select("*")
            ->from("acc_m_akun")
            ->where("tipe", "=", "Cash & Bank")
            ->where("is_tipe", "=", 0)
            ->where("is_deleted", "=", 0)
            ->findAll();
    return successResponse($response, [
        'list' => $models
    ]);
});
$app->get('/acc/t_transfer/index', function ($request, $response) {
    $params = $request->getParams();
    $tableuser = tableUser();
    $db = $this->db;
    $db->select("
                acc_transfer.*, 
                lok1.nama as namaLokAsal, 
                lok1.kode as kodeLokAsal, 
                lok2.nama as namaLokTujuan, 
                lok2.kode as kodeLokTujuan, 
                akun2.id as idTujuan, 
                akun2.nama as namaTujuan, 
                akun2.kode as kodeTujuan, 
                akun1.id as idAsal, 
                akun1.nama as namaAsal, 
                akun1.kode as kodeAsal,
                " . $tableuser . ".nama as namaUser
            ")
            ->from("acc_transfer")
            ->join("join", $tableuser, $tableuser . ".id = acc_transfer.created_by")
            ->join("join", "acc_m_akun akun1", "acc_transfer.m_akun_asal_id = akun1.id")
            ->join("join", "acc_m_akun akun2", "acc_transfer.m_akun_tujuan_id = akun2.id")
            ->join("join", "acc_m_lokasi lok1", "acc_transfer.m_lokasi_asal_id = lok1.id")
            ->join("join", "acc_m_lokasi lok2", "acc_transfer.m_lokasi_tujuan_id = lok2.id")
            ->orderBy('acc_transfer.tanggal DESC')
            ->orderBy('acc_transfer.created_at DESC');
    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);
        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("acc_transfer.is_deleted", '=', $val);
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
    foreach ($models as $key => $val) {
        $models[$key] = (array) $val;
        $models[$key]['tanggal'] = date("Y-m-d h:i:s", strtotime($val->tanggal));
        $models[$key]['tanggal_formated'] = date("d-m-Y", strtotime($val->tanggal));
        $models[$key]['created_at'] = date("d-m-Y h:i", $val->created_at);
        $models[$key]['m_akun_asal_id'] = ["id" => $val->idAsal, "nama" => $val->namaAsal, "kode" => $val->kodeAsal];
        $models[$key]['m_akun_tujuan_id'] = ["id" => $val->idTujuan, "nama" => $val->namaTujuan, "kode" => $val->kodeTujuan];
        $models[$key]['m_lokasi_asal_id'] = ["id" => $val->m_lokasi_asal_id, "nama" => $val->namaLokAsal, "kode" => $val->kodeLokAsal];
        $models[$key]['m_lokasi_tujuan_id'] = ["id" => $val->m_lokasi_tujuan_id, "nama" => $val->namaLokTujuan, "kode" => $val->kodeLokTujuan];
        $models[$key]['status'] = ucfirst($val->status);
        $models[$key]['total'] = number_format(intval($val->total));
    }

    $a = getMasterSetting();
    $testing = !empty($a->posisi_transfer) ? json_decode($a->posisi_transfer) : [];

    return successResponse($response, [
        'list' => $models,
        'totalItems' => $totalItem,
        'base_url' => str_replace('api/', '', config('SITE_URL')),
        'field' => $testing
    ]);
});
$app->post('/acc/t_transfer/save', function ($request, $response) {
    $params = $request->getParams();
    $data = $params;
    $sql = $this->db;
    $validasi = validasi($data['form']);
    if ($validasi === true) {
        /*
         * akun pengimbang
         */
        $getakun = getPemetaanAkun("Pengimbang Neraca");
        /*
         * kode
         */
//        echo date("y", strtotime($data['form']['tanggal'])); die();
        $get_bulan = date("m", strtotime($data['form']['tanggal']));
        $get_tahun = date("Y", strtotime($data['form']['tanggal']));
        $kode = generateNoTransaksi("transfer", $data['form']['m_lokasi_asal_id']['kode'], null, $get_bulan, $get_tahun);
        $insert['no_urut'] = (empty($kode)) ? 1 : ((int) substr($kode, -5));
        $insert['m_lokasi_asal_id'] = $data['form']['m_lokasi_asal_id']['id'];
        $insert['m_lokasi_tujuan_id'] = $data['form']['m_lokasi_tujuan_id']['id'];
        $insert['m_akun_asal_id'] = $data['form']['m_akun_asal_id']['id'];
        $insert['m_akun_tujuan_id'] = $data['form']['m_akun_tujuan_id']['id'];
        $insert['tanggal'] = date("Y-m-d h:i:s", strtotime($data['form']['tanggal']));
        $insert['total'] = $data['form']['total'];
        $insert['status'] = $data['form']['status'];
        $insert['keterangan'] = (isset($data['form']['keterangan']) && !empty($data['form']['keterangan']) ? $data['form']['keterangan'] : '');
        if (isset($data['form']['id']) && !empty($data['form']['id'])) {

            $cek_data = $sql->select("tanggal")->from("acc_transfer")->where("id", "=", $data['form']['id'])->find();

//            print_r($cek_data);die;

            if (date("m", strtotime($cek_data->tanggal)) != date("m", strtotime($insert['tanggal']))) {
//                echo 1; die;
                $insert['no_transaksi'] = $kode;
                $insert['no_urut'] = (empty($kode)) ? 1 : ((int) substr($kode, -5));
            } else {
//                echo 2; die;
                $insert['no_urut'] = $params['form']['no_urut'];
                $insert['no_transaksi'] = $params['form']['no_transaksi'];
            }

//            print_r($insert);
//            die;

            $model = $sql->update("acc_transfer", $insert, ["id" => $data['form']['id']]);
        } else {
            $insert['no_transaksi'] = $kode;
            $model = $sql->insert("acc_transfer", $insert);
        }
        $deletetransdetail = $sql->delete("acc_trans_detail", ["reff_id" => $model->id, "reff_type" => "acc_transfer"]);
        /*
         * deklarasi untuk simpan ke transdetail
         */
        $transDetail = [];
        $insert2['m_lokasi_jurnal_id'] = (isset($data['form']['m_lokasi_asal_id']['id']) ? $data['form']['m_lokasi_asal_id']['id'] : '');
        $insert2['m_lokasi_id'] = (isset($data['form']['m_lokasi_tujuan_id']['id']) ? $data['form']['m_lokasi_tujuan_id']['id'] : '');
        $insert2['m_akun_id'] = (isset($data['form']['m_akun_tujuan_id']['id']) ? $data['form']['m_akun_tujuan_id']['id'] : '');
        $insert2['tanggal'] = date("Y-m-d", strtotime($data['form']['tanggal']));
        $insert2['debit'] = (isset($data['form']['total']) ? $data['form']['total'] : '');
        $insert2['reff_type'] = "acc_transfer";
        $insert2['reff_id'] = $model->id;
        $insert2['keterangan'] = (isset($data['form']['keterangan']) && !empty($data['form']['keterangan']) ? $data['form']['keterangan'] : '');
        $insert2['kode'] = $model->no_transaksi;
        $transDetail[] = $insert2;
        $insert3['m_lokasi_jurnal_id'] = (isset($data['form']['m_lokasi_asal_id']['id']) ? $data['form']['m_lokasi_asal_id']['id'] : '');
        $insert3['m_lokasi_id'] = (isset($data['form']['m_lokasi_asal_id']['id']) ? $data['form']['m_lokasi_asal_id']['id'] : '');
        $insert3['m_akun_id'] = (isset($data['form']['m_akun_asal_id']['id']) ? $data['form']['m_akun_asal_id']['id'] : '');
        $insert3['tanggal'] = date("Y-m-d", strtotime($data['form']['tanggal']));
        $insert3['kredit'] = (isset($data['form']['total']) ? $data['form']['total'] : '');
        $insert3['reff_type'] = "acc_transfer";
        $insert3['reff_id'] = $model->id;
        $insert3['keterangan'] = (isset($data['form']['keterangan']) && !empty($data['form']['keterangan']) ? $data['form']['keterangan'] : '');
        $insert3['kode'] = $model->no_transaksi;
        $transDetail[] = $insert3;
        /*
         * Simpan array trans detail ke database jika simpan dan kunci
         */
        if ($data['form']['status'] == "terposting") {
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
$app->post('/acc/t_transfer/delete', function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    $model = $db->delete("acc_transfer", ['id' => $data['id']]);
    $model = $db->delete("acc_trans_detail", ["reff_type" => "acc_transfer", "reff_id" => $data['id']]);
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});

$app->post("/acc/t_transfer/savePosition", function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;

    $data['posisi_transfer'] = json_encode($data);
    try {
        $model = $db->update("acc_m_setting", $data, ["id" => 1]);
        return successResponse($response, $model);
    } catch (Exception $e) {
        return unprocessResponse($response, ["Terjadi kesalahan pada server"]);
    }
});

