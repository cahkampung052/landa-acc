<?php

function validasi($data, $custom = array()) {
    $validasi = array(
        'm_lokasi_id' => 'required',
        'm_akun_id' => 'required',
        'tanggal' => 'required',
        'total' => 'required',
    );
    GUMP::set_field_name("m_akun_id", "Masuk ke akun");
    GUMP::set_field_name("m_lokasi_id", "Lokasi");
    GUMP::set_field_name("total", "Detail");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

/**
 * Upload gambar
 */
$app->post('/acc/t_penerimaan/upload/{folder}', function ($request, $response) {
    $folder = $request->getAttribute('folder');
    $params = $request->getParams();
    if (!empty($_FILES)) {
        $tempPath = $_FILES['file']['tmp_name'];
        $sql = $this->db;
        $id_dokumen = $sql->find("select * from acc_dokumen_foto order by id desc");
        $gid = (isset($id_dokumen->id)) ? $id_dokumen->id + 1 : 1;
        $newName = $gid . "_" . urlParsing($_FILES['file']['name']);
        $uploadPath = "file/penerimaan/" . date('Y') . "/" . str_replace("0", "", date("m"));
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }
        move_uploaded_file($tempPath, $uploadPath . DIRECTORY_SEPARATOR . $newName);
        if ($params['id'] == "undefined" || empty($params['id'])) {
            $pengeluaran_id = $sql->find("select * from acc_pemasukan order by id desc");
            $pid = (isset($pengeluaran_id->id)) ? $pengeluaran_id->id : 1;
        } else {
            $pid = $params['id'];
        }
        $file = $uploadPath;
        if (file_exists($file)) {
            $answer = array('answer' => 'File transfer completed', 'img' => $newName, 'id' => $gid);
            if ($answer['answer'] == "File transfer completed") {
                $data = array(
                    'id' => $gid,
                    'acc_pemasukan_id' => $pid,
                    'img' => $newName,
                );
                $create_foto = $sql->insert('acc_dokumen_foto', $data);
            }
            echo json_encode($answer);
        } else {
            if (file_exists($uploadPath)) {
                $answer = array('answer' => 'File transfer completed', 'img' => $newName, 'id' => $gid);
            } else {
                echo $uploadPath;
            }
        }
    } else {
        echo 'No files';
    }
});
/**
 * Ambil list gambar
 */
$app->get('/acc/t_penerimaan/listgambar/{id}', function ($request, $response) {
    $id = $request->getAttribute('id');
    $sql = $this->db;
    $model = $sql->select("*")
            ->from("acc_dokumen_foto")
            ->where("acc_pemasukan_id", "=", $id)
            ->findAll();
    return successResponse($response, ["model" => $model, "url" => "api/file/penerimaan/" . date("Y") . "/" . str_replace("0", "", date("m")) . "/"]);
});
/**
 * Hapus gambar
 */
$app->post('/acc/t_penerimaan/removegambar', function ($request, $response) {
    $params = $request->getParams();
    $sql = $this->db;
    $delete = $sql->delete('acc_dokumen_foto', array('id' => $params['id'], "img" => $params['img']));
    unlink(__DIR__ . "/../../../file/penerimaan/" . date('Y') . "/" . str_replace("0", "", date("m")) . "/" . $params['img']);
});
/**
 * Ambil / generate kode
 */
$app->get('/acc/t_penerimaan/kode/{kode}', function ($request, $response) {
    $kode_unit_1 = $request->getAttribute('kode');
    $db = $this->db;
    $model = $db->find("select * from acc_pemasukan order by id desc");
    $urut = (empty($model)) ? 1 : ((int) substr($model->no_urut, -3)) + 1;
    $no_urut = substr('0000' . $urut, -3);
    return successResponse($response, ["kode" => $kode_unit_1 . date("y") . "PMSK" . $no_urut, "urutan" => $no_urut]);
});
/**
 * Ambil detail penerimaan
 */
$app->get('/acc/t_penerimaan/getDetail', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    $models = $db->select("
                acc_pemasukan_det.*, 
                acc_m_akun.kode as kodeAkun, 
                acc_m_akun.nama as namaAkun,
                acc_m_lokasi.kode as kodeLokasi,
                acc_m_lokasi.nama as namaLokasi
            ")
            ->from("acc_pemasukan_det")
            ->join("join", "acc_m_akun", "acc_m_akun.id = acc_pemasukan_det.m_akun_id")
            ->join("join", "acc_m_lokasi", "acc_m_lokasi.id = acc_pemasukan_det.m_lokasi_id")
            ->where("acc_pemasukan_id", "=", $params['id'])
            ->findAll();
    foreach ($models as $key => $val) {
        $val->m_akun_id = ["id" => $val->m_akun_id, "kode" => $val->kodeAkun, "nama" => $val->namaAkun];
        $val->m_lokasi_id = ["id" => $val->m_lokasi_id, "kode" => $val->kodeLokasi, "nama" => $val->namaLokasi];
    }
    return successResponse($response, ['list' => $models]);
});
/**
 * Ambil riwayat penerimaan
 */
$app->get('/acc/t_penerimaan/index', function ($request, $response) {
    $params = $request->getParams();
    $tableuser = tableUser();
    $db = $this->db;
    $db->select("
                acc_pemasukan.*, 
                acc_m_lokasi.kode as kodeLokasi, 
                acc_m_lokasi.nama as namaLokasi, 
                " . $tableuser . ".nama as namaUser, 
                acc_m_akun.kode as kodeAkun, 
                acc_m_akun.nama as namaAkun, 
                acc_m_kontak.nama as namaCus,
                acc_m_kontak.type as typeCus
            ")
            ->from("acc_pemasukan")
            ->join("left join", $tableuser, $tableuser . ".id = acc_pemasukan.created_by")
            ->join("left join", "acc_m_akun", "acc_pemasukan.m_akun_id = acc_m_akun.id")
            ->join("left join", "acc_m_lokasi", "acc_m_lokasi.id = acc_pemasukan.m_lokasi_id")
            ->join("left join", "acc_m_kontak", "acc_m_kontak.id = acc_pemasukan.m_kontak_id")
            ->orderBy('acc_pemasukan.tanggal DESC')
            ->orderBy('acc_pemasukan.created_at DESC');
    /**
     * Set filter
     */
    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);
        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("acc_pemasukan.is_deleted", '=', $val);
            } else {
                $db->where($key, 'LIKE', $val);
            }
        }
    }
    /**
     * Set limit
     */
    if (isset($params['limit']) && !empty($params['limit'])) {
        $db->limit($params['limit']);
    }
    /**
     * Set offset
     */
    if (isset($params['offset']) && !empty($params['offset'])) {
        $db->offset($params['offset']);
    }
    $models = $db->findAll();
    $totalItem = $db->count();
    /*
     * ambil master setting
     */
    $setting = getMasterSetting();
    foreach ($models as $key => $val) {
        $models[$key] = (array) $val;
        $models[$key]['tanggal'] = date("Y-m-d", $val->modified_at);
        $models[$key]['tanggal2'] = date("Y-m-d", strtotime($val->tanggal));
        $models[$key]['tanggal_formated'] = date("d-m-Y", strtotime($val->tanggal));
        $models[$key]['created_at'] = date("d-m-Y h:i", $val->created_at);
        $models[$key]['m_akun_id'] = ["id" => $val->m_akun_id, "nama" => $val->namaAkun, "kode" => $val->kodeAkun];
        $models[$key]['m_lokasi_id'] = ["id" => $val->m_lokasi_id, "nama" => $val->namaLokasi, "kode" => $val->kodeLokasi];
        $models[$key]['m_kontak_id'] = ["id" => $val->m_kontak_id, "nama" => $val->namaCus, "type" => ucfirst($val->typeCus)];
        $models[$key]['status'] = ucfirst($val->status);
        $models[$key]['tanggal_setting'] = $setting->tanggal;
        $models[$key]['grandtotal'] = intval($val->total) + intval($val->ppn);
    }
    return successResponse($response, [
        'list' => $models,
        'totalItems' => $totalItem,
        'base_url' => str_replace('api/', '', config('SITE_URL'))
    ]);
});
/**
 * Simpan penerimaan
 */
$app->post('/acc/t_penerimaan/save', function ($request, $response) {
    $params = $request->getParams();
    
//    print_r($params);die;
    
    
    $sql = $this->db;
    $validasi = validasi($params['form']);
    if ($validasi === true) {
        //ganti preffix kode berdasarkan nama parent diatasnya
        $preffix = $sql->select("*")->from("acc_m_akun")->where("id","=",$params['form']['m_akun_id']['parent_id'])->find();
        if ($preffix) {
            if ($preffix->nama == 'CASH ON HAND') {
                $string = "KM";
            }else{
                $fitst_char = strtoupper(substr($preffix->nama, 0,1));
                $string = $fitst_char."M";
            }
        }

        /**
         * Generate kode penerimaan
         */
        $kode = generateNoTransaksi("penerimaan", $params['form']['m_lokasi_id']['kode'], $string);
        $kode = str_replace("BM", $string, $kode);
        $penerimaan['no_urut'] = (empty($kode)) ? 1 : ((int) substr($kode, -5));
        /**
         * Simpan penerimaan
         */
        $penerimaan['m_lokasi_id'] = $params['form']['m_lokasi_id']['id'];
        $penerimaan['m_akun_id'] = $params['form']['m_akun_id']['id'];
        $penerimaan['m_kontak_id'] = (isset($params['form']['m_kontak_id']['id']) && !empty($params['form']['m_kontak_id']['id'])) ? $params['form']['m_kontak_id']['id'] : '';
        $penerimaan['keterangan'] = (isset($params['form']['keterangan']) && !empty($params['form']['keterangan']) ? $params['form']['keterangan'] : '');
        $penerimaan['tanggal'] = date("Y-m-d h:i:s", strtotime($params['form']['tanggal']));
        $penerimaan['total'] = $params['form']['total'];
//        $penerimaan['total'] = $params['form']['total'] - $params['form']['ppn'];
//        $penerimaan['ppn'] = $params['form']['ppn'];
        $penerimaan['status'] = $params['form']['status'];
        if (isset($params['form']['id']) && !empty($params['form']['id'])) {
            $penerimaan['no_urut'] = $params['form']['no_urut'];
            $penerimaan['no_transaksi'] = $params['form']['no_transaksi'];
            $model = $sql->update("acc_pemasukan", $penerimaan, ["id" => $params['form']['id']]);
            /**
             * Hapus pemasukan detail
             */
            $sql->delete("acc_pemasukan_det", ["acc_pemasukan_id" => $model->id]);
            /**
             * Hapus trans detail
             */
            $sql->delete("acc_trans_detail", ["reff_type" => "acc_pemasukan", "reff_id" => $model->id]);
        } else {
            $penerimaan['no_transaksi'] = $kode;
            $model = $sql->insert("acc_pemasukan", $penerimaan);
        }
        /**
         * Masukkan ke dalam array trans detail
         */
        $transDetail[0]['m_lokasi_id'] = $model->m_lokasi_id;
        $transDetail[0]['m_akun_id'] = $model->m_akun_id;
        $transDetail[0]['m_kontak_id'] = $model->m_kontak_id;
        $transDetail[0]['debit'] = $model->total;
        $transDetail[0]['kode'] = $model->no_transaksi;
        $transDetail[0]['keterangan'] = $model->keterangan;
        $transDetail[0]['reff_id'] = $model->id;
        $transDetail[0]['tanggal'] = date("Y-m-d", strtotime($model->tanggal));
        $transDetail[0]['reff_type'] = "acc_pemasukan";
        $transDetail[0]['m_lokasi_jurnal_id'] = $model->m_lokasi_id;
        /**
         * Simpan ke pemasukan detail
         */
        if (isset($params['detail']) && !empty($params['detail'])) {
            foreach ($params['detail'] as $key => $val) {
                $detail['acc_pemasukan_id'] = $model->id;
                $detail['m_lokasi_id'] = (isset($val['m_lokasi_id']['id']) ? $val['m_lokasi_id']['id'] : '');
                $detail['m_akun_id'] = (isset($val['m_akun_id']['id']) ? $val['m_akun_id']['id'] : '');
                $detail['kredit'] = (isset($val['kredit']) ? $val['kredit'] : '');
                $detail['keterangan'] = (isset($val['keterangan']) && !empty($val['keterangan']) ? $val['keterangan'] : '');
                $modeldetail = $sql->insert("acc_pemasukan_det", $detail);
                /**
                 * Simpan trans detail ke array
                 */
                $transDetail[$key + 1]['m_akun_id'] = $modeldetail->m_akun_id;
                $transDetail[$key + 1]['m_kontak_id'] = $model->m_kontak_id;
                $transDetail[$key + 1]['m_lokasi_id'] = $modeldetail->m_lokasi_id;
                $transDetail[$key + 1]['kredit'] = $modeldetail->kredit;
                $transDetail[$key + 1]['keterangan'] = $modeldetail->keterangan;
                $transDetail[$key + 1]['kode'] = $model->no_transaksi;
                $transDetail[$key + 1]['reff_id'] = $model->id;
                $transDetail[$key + 1]['tanggal'] = date("Y-m-d", strtotime($model->tanggal));
                $transDetail[$key + 1]['reff_type'] = "acc_pemasukan";
                $transDetail[$key + 1]['m_lokasi_jurnal_id'] = $model->m_lokasi_id;
            }
        }
        /**
         * Simpan array trans detail ke database jika simpan dan kunci
         */
        if ($params['form']['status'] == "terposting") {
            insertTransDetail($transDetail);
        }
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, $validasi);
    }
});
$app->post('/acc/t_penerimaan/delete', function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    $model = $db->delete("acc_pemasukan", ['id' => $data['id']]);
    $model = $db->delete("acc_pemasukan_det", ['acc_pemasukan_id' => $data['id']]);
    $model = $db->delete("acc_trans_detail", ['reff_type' => 'acc_pemasukan', 'reff_id' => $data['id']]);
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});
$app->get('/acc/t_penerimaan/print', function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    $detail = $db->select("
                    acc_pemasukan_det.*, 
                    acc_m_akun.id as idAkun, 
                    acc_m_akun.kode as kodeAkun, 
                    acc_m_akun.nama as namaAkun
                ")
            ->from("acc_pemasukan_det")
            ->join("join", "acc_m_akun", "acc_m_akun.id = acc_pemasukan_det.m_akun_id")
            ->where("acc_pemasukan_id", "=", $data['id'])
            ->findAll();
    foreach ($detail as $key => $val) {
        $val->m_akun_id = ["id" => $val->m_akun_id, "kode" => $val->kodeAkun, "nama" => $val->namaAkun];
    }
    $data['tanggalsekarang'] = date("d-m-Y H:i");
    $data['terbilang'] = terbilang($data['total']);
    $data['total'] = (int) $data['total'];
    $data['url_img'] = config('SITE_IMG');
    $setting = getMasterSetting();
    $template = $setting->print_penerimaan;
    $template = str_replace("{start_detail}", "{%for key, val in detail%}", $template);
    $template = str_replace("{end}", "{%endfor%}", $template);
    $view = twigViewPath();
    $content = $view->fetchFromString($template, [
        "data" => $data,
        "detail" => (array) $detail,
    ]);
    echo $content;
    echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
});
/*
 * template print
 */
$app->get("/acc/t_penerimaan/getTemplate", function ($request, $response) {
    $setting = getMasterSetting();
    return successResponse($response, $setting->print_penerimaan);
});
$app->post("/acc/t_penerimaan/saveTemplate", function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    try {
        $model = $db->update("acc_m_setting", $data, ["id" => 1]);
        return successResponse($response, $model);
    } catch (Exception $e) {
        return unprocessResponse($response, ["Terjadi kesalahan pada server"]);
    }
});
