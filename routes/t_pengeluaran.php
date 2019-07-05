<?php

function validasi($data, $custom = array()) {
    $validasi = array(
        'm_lokasi_id' => 'required',
        'm_akun_id' => 'required',
        'tanggal' => 'required',
        'total' => 'required',
    );
    GUMP::set_field_name("m_akun_id", "Keluar dari akun");
    GUMP::set_field_name("m_lokasi_id", "Lokasi");
    GUMP::set_field_name("total", "Detail");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

/*
 * Upload Gambar
 */
$app->post('/acc/t_pengeluaran/upload/{folder}', function ($request, $response) {
    $folder = $request->getAttribute('folder');
    $params = $request->getParams();
//    print_r($params);die();
    if (!empty($_FILES)) {
        $tempPath = $_FILES['file']['tmp_name'];
        $sql = $this->db;
        $id_dokumen = $sql->find("select * from acc_dokumen_foto order by id desc");
        $gid = (isset($id_dokumen->id)) ? $id_dokumen->id + 1 : 1;
        $newName = $gid . "_" . urlParsing($_FILES['file']['name']);
        $uploadPath = "file/pengeluaran/" . date('Y') . "/" . str_replace("0", "", date("m"));

        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }
        move_uploaded_file($tempPath, $uploadPath . DIRECTORY_SEPARATOR . $newName);

        if ($params['id'] == "undefined" || empty($params['id'])) {
            $pengeluaran_id = $sql->find("select * from acc_pengeluaran order by id desc");
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
                    'acc_pengeluaran_id' => $pid,
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

/*
 * Ambil list gambar
 */
$app->get('/acc/t_pengeluaran/listgambar/{id}', function ($request, $response) {
    $id = $request->getAttribute('id');
    $sql = $this->db;
    $model = $sql->select("*")->from("acc_dokumen_foto")->where("acc_pengeluaran_id", "=", $id)->findAll();
    return successResponse($response, ["model" => $model, "url" => "api/file/pengeluaran/" . date("Y") . "/" . str_replace("0", "", date("m")) . "/"]);
});

/*
 * Hapus gambar
 */
$app->post('/acc/t_pengeluaran/removegambar', function ($request, $response) {
    $params = $request->getParams();
    $sql = $this->db;

    $delete = $sql->delete('acc_dokumen_foto', array('id' => $params['id'], "img" => $params['img']));
    unlink(__DIR__ . "/../../../file/pengeluaran/" . date('Y') . "/" . str_replace("0", "", date("m")) . "/" . $params['img']);
});


/*
 * Ambil / generate kode
 */
$app->get('/acc/t_pengeluaran/kode/{kode}', function ($request, $response) {

    $kode_unit_1 = $request->getAttribute('kode');
    $db = $this->db;

    $model = $db->find("select * from acc_pengeluaran order by id desc");
    $urut = (empty($model)) ? 1 : ((int) substr($model->no_urut, -3)) + 1;
    $no_urut = substr('0000' . $urut, -3);
    return successResponse($response, ["kode" => $kode_unit_1 . date("y") . "PNGL" . $no_urut, "urutan" => $no_urut]);
});

/*
 * Ambil detail pengeluaran
 */
$app->get('/acc/t_pengeluaran/getDetail', function ($request, $response) {
    $params = $request->getParams();
//    print_r($params);die();
    $db = $this->db;
    $models = $db->select("acc_pengeluaran_det.*, acc_m_akun.kode as kodeAkun, acc_m_akun.nama as namaAkun, acc_m_lokasi.nama as namaLokasi")
            ->from("acc_pengeluaran_det")
            ->join("join", "acc_m_akun", "acc_m_akun.id = acc_pengeluaran_det.m_akun_id")
            ->join("join", "acc_m_lokasi", "acc_m_lokasi.id = acc_pengeluaran_det.m_lokasi_id")
            ->where("acc_pengeluaran_id", "=", $params['id'])
            ->findAll();

    foreach ($models as $key => $val) {
        $val->m_akun_id = ["id" => $val->m_akun_id, "kode" => $val->kodeAkun, "nama" => $val->namaAkun];
        $val->m_lokasi_id = ["id" => $val->m_lokasi_id, "nama" => $val->namaLokasi];
    }


    return successResponse($response, [
        'list' => $models
    ]);
});

/*
 * ambil riwayat pengeluaran
 */
$app->get('/acc/t_pengeluaran/index', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    $db->select("
            acc_pengeluaran.*, 
            acc_m_lokasi.kode as kodeLokasi, 
            acc_m_lokasi.nama as namaLokasi, 
            acc_m_user.nama as namaUser, 
            acc_m_akun.kode as kodeAkun, 
            acc_m_akun.nama as namaAkun, 
            acc_m_supplier.nama as namaSup
        ")
            ->from("acc_pengeluaran")
            ->join("left join", "acc_m_user", "acc_pengeluaran.created_by = acc_m_user.id")
            ->join("left join", "acc_m_akun", "acc_pengeluaran.m_akun_id = acc_m_akun.id")
            ->join("left join", "acc_m_lokasi", "acc_m_lokasi.id = acc_pengeluaran.m_lokasi_id")
            ->join("left join", "acc_m_supplier", "acc_m_supplier.id = acc_pengeluaran.m_supplier_id")
            ->orderBy('acc_pengeluaran.tanggal DESC')
            ->orderBy('acc_pengeluaran.created_at DESC');

    /*
     * set filter
     */
    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);

        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("acc_pengeluaran.is_deleted", '=', $val);
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
        $models[$key]['tanggal'] = date("d-m-Y h:i:s", strtotime($val->tanggal));
        $models[$key]['created_at'] = date("d-m-Y h:i:s", $val->created_at);
        $models[$key]['m_akun_id'] = ["id" => $val->m_akun_id, "nama" => $val->namaAkun, "kode" => $val->kodeAkun];
        $models[$key]['m_lokasi_id'] = ["id" => $val->m_lokasi_id, "nama" => $val->namaLokasi, "kode" => $val->kodeLokasi];
        $models[$key]['m_supplier_id'] = ["id" => $val->m_supplier_id, "nama" => $val->namaSup];
    }

    return successResponse($response, [
        'list' => $models,
        'totalItems' => $totalItem,
        'base_url' => str_replace('api/', '', config('SITE_URL'))
    ]);
});

/*
 * Simpan pengeluaran
 */
$app->post('/acc/t_pengeluaran/save', function ($request, $response) {

    $params = $request->getParams();
    $sql = $this->db;
    $validasi = validasi($params['form']);
    if ($validasi === true) {
        /**
         * Generate kode pengeluaran
         */
        $getNoUrut = $sql->select("*")->from("acc_pengeluaran")->orderBy("no_urut DESC")->find();
        $penerimaan['no_urut'] = 1;
        $urut = 1;
        if ($getNoUrut) {
            $penerimaan['no_urut'] = $getNoUrut->no_urut + 1;
            $urut = ((int) substr($getNoUrut->no_urut, -4)) + 1;
        }
        $no_urut = substr('0000' . $urut, -4);
        $kode = $params['form']['m_lokasi_id']['kode'] . date("y") . "PNGL" . $no_urut;
        /**
         * Simpan penerimaan
         */
        $pengeluaran['m_lokasi_id'] = $params['form']['m_lokasi_id']['id'];
        $pengeluaran['m_akun_id'] = $params['form']['m_akun_id']['id'];
        $pengeluaran['m_supplier_id'] = (isset($params['form']['m_supplier_id']['id']) && !empty($params['form']['m_supplier_id']['id'])) ? $params['form']['m_supplier_id']['id'] : '';
        $pengeluaran['dibayar_kepada'] = (isset($params['form']['dibayar_kepada']) && !empty($params['form']['dibayar_kepada']) ? $params['form']['dibayar_kepada'] : '');
        $pengeluaran['tanggal'] = date("Y-m-d h:i:s", strtotime($params['form']['tanggal']));
        $pengeluaran['total'] = $params['form']['total'];
        if (isset($params['form']['id']) && !empty($params['form']['id'])) {
            $pengeluaran['no_urut'] = $params['form']['no_urut'];
            $pengeluaran['no_transaksi'] = $params['form']['no_transaksi'];
            $model = $sql->update("acc_pemasukan", $pengeluaran, ["id" => $params['form']['id']]);
            /**
             * Hapus pengeluaran detail
             */
            $sql->delete("acc_pengeluaran_det", ["acc_pengeluaran_id" => $model->id]);
            /**
             * Hapus trans detail
             */
            $sql->delete("acc_trans_detail", ["reff_type" => "acc_pengeluaran", "reff_id" => $model->id]);
        } else {
            $pengeluaran['no_transaksi'] = $kode;
            $model = $sql->insert("acc_pengeluaran", $pengeluaran);
        }
        /**
         * Masukkan ke dalam array trans detail
         */
        $transDetail[0]['m_lokasi_id'] = $model->m_lokasi_id;
        $transDetail[0]['m_akun_id'] = $model->m_akun_id;
        $transDetail[0]['m_supplier_id'] = $model->m_supplier_id;
        $transDetail[0]['tanggal'] = date("Y-m-d", strtotime($model->tanggal));
        $transDetail[0]['kredit'] = $model->total;
        $transDetail[0]['reff_type'] = "acc_pengeluaran";
        $transDetail[0]['kode'] = $model->no_transaksi;
        $transDetail[0]['reff_id'] = $model->id;
        /**
         * Simpan ke pemasukan detail
         */
        if (isset($params['detail']) && !empty($params['detail'])) {
            foreach ($params['detail'] as $key => $val) {
                $detail['m_akun_id'] = $val['m_akun_id']['id'];
                $detail['m_lokasi_id'] = $model->m_lokasi_id;
                $detail['debit'] = $val['debit'];
                $detail['acc_pengeluaran_id'] = $model->id;
                $detail['keterangan'] = (isset($val['keterangan']) && !empty($val['keterangan']) ? $val['keterangan'] : '');
                $modeldetail = $sql->insert("acc_pengeluaran_det", $detail);

                /**
                 * Simpan trans detail ke array
                 */
                $transDetail[$key + 1]['m_akun_id'] = $modeldetail->m_akun_id;
                $transDetail[$key + 1]['m_lokasi_id'] = $modeldetail->m_lokasi_id;
                $transDetail[$key + 1]['tanggal'] = date("Y-m-d", strtotime($model->tanggal));
                $transDetail[$key + 1]['debit'] = $modeldetail->debit;
                $transDetail[$key + 1]['keterangan'] = $modeldetail->keterangan;
                $transDetail[$key + 1]['kode'] = $model->no_transaksi;
                $transDetail[$key + 1]['reff_type'] = "acc_pengeluaran";
                $transDetail[$key + 1]['reff_id'] = $model->id;
            }
        }
        /**
         * Simpan array trans detail ke database
         */
        insertTransDetail($transDetail);
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, $validasi);
    }
});

/*
 * Hapus pengeluaran
 */
$app->post('/acc/t_pengeluaran/delete', function ($request, $response) {

    $data = $request->getParams();
    $db = $this->db;
    $model = $db->delete("acc_pengeluaran", ['id' => $data['id']]);
    $model = $db->delete("acc_pengeluaran_det", ['acc_pengeluaran_id' => $data['id']]);
    $model = $db->delete("acc_trans_detail", ['reff_type' => 'acc_pengeluaran', 'reff_id' => $data['id']]);
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});
