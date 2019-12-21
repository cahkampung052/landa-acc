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
    $model = $sql->select("*")
            ->from("acc_dokumen_foto")
            ->where("acc_pengeluaran_id", "=", $id)
            ->findAll();
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
    $db = $this->db;
    $models = $db->select("
                    acc_pengeluaran_det.*, 
                    acc_m_akun.kode as kodeAkun, 
                    acc_m_akun.nama as namaAkun,
                    acc_m_lokasi.kode as kodeLokasi,
                    acc_m_lokasi.nama as namaLokasi
                ")
            ->from("acc_pengeluaran_det")
            ->join("join", "acc_pengeluaran", "acc_pengeluaran.id = acc_pengeluaran_det.acc_pengeluaran_id")
            ->join("join", "acc_m_akun", "acc_m_akun.id = acc_pengeluaran_det.m_akun_id")
            ->join("join", "acc_m_lokasi", "acc_m_lokasi.id = acc_pengeluaran_det.m_lokasi_id")
            ->where("acc_pengeluaran_id", "=", $params['id'])
            ->findAll();
    foreach ($models as $key => $val) {
        $val->m_akun_id = ["id" => $val->m_akun_id, "kode" => $val->kodeAkun, "nama" => $val->namaAkun];
        $val->m_lokasi_id = ["id" => $val->m_lokasi_id, "kode" => $val->kodeLokasi, "nama" => $val->namaLokasi];
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
    $tableuser = tableUser();
    $db = $this->db;
    $db->select("
            acc_pengeluaran.*, 
            acc_m_lokasi.kode as kodeLokasi, 
            acc_m_lokasi.nama as namaLokasi, 
            " . $tableuser . ".nama as namaUser, 
            acc_m_akun.kode as kodeAkun, 
            acc_m_akun.nama as namaAkun, 
            acc_m_kontak.nama as namaSup,
            acc_m_kontak.type as typeSup,
            acc_t_pengajuan.no_proposal
        ")
            ->from("acc_pengeluaran")
            ->join("left join", "acc_t_pengajuan", "acc_t_pengajuan.id = acc_pengeluaran.t_pengajuan_id")
            ->join("left join", $tableuser, $tableuser . ".id = acc_pengeluaran.created_by")
            ->join("left join", "acc_m_akun", "acc_pengeluaran.m_akun_id = acc_m_akun.id")
            ->join("left join", "acc_m_lokasi", "acc_m_lokasi.id = acc_pengeluaran.m_lokasi_id")
            ->join("left join", "acc_m_kontak", "acc_m_kontak.id = acc_pengeluaran.m_kontak_id")
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
            } else if ($key == 'no_transaksi') {
                $db->customWhere("(acc_pengeluaran.no_transaksi LIKE '%$val%' OR acc_t_pengajuan.no_proposal LIKE '%$val%')", "AND");
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
        $models[$key]['tanggal'] = date("Y-m-d", $val->modified_at);
        $models[$key]['tanggal2'] = date("Y-m-d", strtotime($val->tanggal));
        $models[$key]['tanggal_formated'] = date("d-m-Y", strtotime($val->tanggal));
        $models[$key]['created_at'] = date("d-m-Y h:i", $val->created_at);
        $models[$key]['m_akun_id'] = ["id" => $val->m_akun_id, "nama" => $val->namaAkun, "kode" => $val->kodeAkun];
        $models[$key]['m_lokasi_id'] = ["id" => $val->m_lokasi_id, "nama" => $val->namaLokasi, "kode" => $val->kodeLokasi];
        if ($val->m_kontak_id != 0) {
            $models[$key]['m_kontak_id'] = ["id" => $val->m_kontak_id, "nama" => $val->namaSup, "type" => ucfirst($val->typeSup)];
        }
        $models[$key]['status'] = ucfirst($val->status);
        $models[$key]['total'] = number_format(intval($val->total));
    }

    $a = getMasterSetting();
    $testing = !empty($a->posisi_pengeluaran) ? json_decode($a->posisi_pengeluaran) : [];

    return successResponse($response, [
        'list' => $models,
        'totalItems' => $totalItem,
        'base_url' => str_replace('api/', '', config('SITE_URL')),
        'field' => $testing
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
        //ganti preffix kode berdasarkan nama parent diatasnya
//        if (isset($params['form']['m_akun_id']['parent_id'])) {
        $preffix = $sql->select("*")->from("acc_m_akun")->where("id", "=", $params['form']['m_akun_id']['parent_id'])->find();

//        print_r($preffix);die;
        if ($preffix) {
            if ($preffix->nama == 'CASH ON HAND') {
                $string = "KK";
            } else {
                $fitst_char = strtoupper(substr($preffix->nama, 0, 1));
                $string = $fitst_char . "K";
            }
        }
//        print_r($string);die;
        /**
         * Generate kode pengeluaran
         */
        $get_bulan = date("m", strtotime($params['form']['tanggal']));
        $get_tahun = date("Y", strtotime($params['form']['tanggal']));
        $kode = generateNoTransaksi("pengeluaran", $params['form']['m_lokasi_id']['kode'], $string, $get_bulan, $get_tahun);
        $kode = str_replace("PREFFIX", $string, $kode);

//        print_r($kode);
//        die;
//        } else {
//            $kode = generateNoTransaksi("pengeluaran", $params['form']['m_lokasi_id']['kode']);
//        }

        $pengeluaran['no_urut'] = (empty($kode)) ? 1 : ((int) substr($kode, -5));

        /**
         * Simpan pengeluaran
         */
        $pengeluaran['m_lokasi_id'] = $params['form']['m_lokasi_id']['id'];
        $pengeluaran['m_akun_id'] = $params['form']['m_akun_id']['id'];
        $pengeluaran['m_kontak_id'] = (isset($params['form']['m_kontak_id']['id']) && !empty($params['form']['m_kontak_id']['id'])) ? $params['form']['m_kontak_id']['id'] : '';
        if (isset($params['form']['penerima'])) {
            $pengeluaran['penerima'] = (isset($params['form']['penerima']) && !empty($params['form']['penerima'])) ? $params['form']['penerima'] : "";
        } else {
            $pengeluaran['penerima'] = (isset($params['form']['m_kontak_id']['id']) && !empty($params['form']['m_kontak_id']['id'])) ? $params['form']['m_kontak_id']['nama'] : '';
        }
        $pengeluaran['tanggal'] = date("Y-m-d h:i:s", strtotime($params['form']['tanggal']));
        $pengeluaran['total'] = $params['form']['total'];
        $pengeluaran['t_pengajuan_id'] = (isset($params['form']['t_pengajuan_id']) && !empty($params['form']['t_pengajuan_id']) ? $params['form']['t_pengajuan_id'] : "");
        $pengeluaran['status'] = $params['form']['status'];

        foreach ($params['detail'] as $key => $value) {
            $keterangan[$key] = $value['keterangan'];
        }
        $keteranganPengeluaran = join("<br>", $keterangan);
        $pengeluaran['keterangan'] = (isset($keteranganPengeluaran) && !empty($keteranganPengeluaran) ? $keteranganPengeluaran : NULL);

        /**
         * update atau input pengeluaran
         */
        
//        print_r($params['form']);die;
        if (isset($params['form']['id']) && !empty($params['form']['id'])) {
            $pengeluaran['no_urut'] = $params['form']['no_urut'];
            $pengeluaran['no_transaksi'] = $params['form']['no_transaksi'];
            $model = $sql->update("acc_pengeluaran", $pengeluaran, ["id" => $params['form']['id']]);
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
         * Simpan ke pengeluaran detail
         */
        $index = 0;
        if (isset($params['detail']) && !empty($params['detail'])) {
            foreach ($params['detail'] as $key => $val) {
                $detail['acc_pengeluaran_id'] = $model->id;
                $detail['debit'] = (isset($val['debit']) ? $val['debit'] : '');
                $detail['m_akun_id'] = (isset($val['m_akun_id']['id']) ? $val['m_akun_id']['id'] : '');
                $detail['m_lokasi_id'] = (isset($val['m_lokasi_id']['id']) ? $val['m_lokasi_id']['id'] : '');
                $detail['keterangan'] = (isset($val['keterangan']) && !empty($val['keterangan']) ? $val['keterangan'] : '');
                $modeldetail = $sql->insert("acc_pengeluaran_det", $detail);
                /**
                 * Simpan trans detail ke array
                 */
                $transDetail[$index]['m_akun_id'] = $modeldetail->m_akun_id;
                $transDetail[$index]['m_kontak_id'] = $model->m_kontak_id;
                $transDetail[$index]['m_lokasi_id'] = $modeldetail->m_lokasi_id;
                $transDetail[$index]['tanggal'] = date("Y-m-d", strtotime($model->tanggal));
                $transDetail[$index]['debit'] = $modeldetail->debit;
                $transDetail[$index]['keterangan'] = $modeldetail->keterangan;
                $transDetail[$index]['kode'] = $model->no_transaksi;
                $transDetail[$index]['reff_type'] = "acc_pengeluaran";
                $transDetail[$index]['reff_id'] = $model->id;
                $transDetail[$index]['m_lokasi_jurnal_id'] = $model->m_lokasi_id;
                $index++;
            }
        }
        /**
         * Masukkan ke dalam array trans detail
         */
        /*
         * ppn
         */
        if ($model->ppn > 0) {
            $akunppn = getPemetaanAkun("PPN");
            $transDetail[$index]['m_lokasi_id'] = $model->m_lokasi_id;
            $transDetail[$index]['m_akun_id'] = $akunppn;
            $transDetail[$index]['m_kontak_id'] = $model->m_kontak_id;
            $transDetail[$index]['tanggal'] = date("Y-m-d", strtotime($model->tanggal));
            $transDetail[$index]['debit'] = $model->ppn;
            $transDetail[$index]['reff_type'] = "acc_pengeluaran";
            $transDetail[$index]['kode'] = $model->no_transaksi;
            $transDetail[$index]['keterangan'] = $model->keterangan;
            $transDetail[$index]['reff_id'] = $model->id;
            $transDetail[$index]['m_lokasi_jurnal_id'] = $model->m_lokasi_id;
        }
        /*
         * header total
         */
        $transDetail[$index + 1]['m_lokasi_id'] = $model->m_lokasi_id;
        $transDetail[$index + 1]['m_akun_id'] = $model->m_akun_id;
        $transDetail[$index + 1]['m_kontak_id'] = $model->m_kontak_id;
        $transDetail[$index + 1]['tanggal'] = date("Y-m-d", strtotime($model->tanggal));
        $transDetail[$index + 1]['kredit'] = $model->total + $model->ppn;
        $transDetail[$index + 1]['reff_type'] = "acc_pengeluaran";
        $transDetail[$index + 1]['kode'] = $model->no_transaksi;
        $transDetail[$index + 1]['keterangan'] = $model->keterangan;
        $transDetail[$index + 1]['reff_id'] = $model->id;
        $transDetail[$index + 1]['m_lokasi_jurnal_id'] = $model->m_lokasi_id;
        /**
         * Simpan array trans detail ke database jika simpan dan kunci
         */
        if ($params['form']['status'] == "terposting") {
            insertTransDetail($transDetail);
            if (isset($params['form']['t_pengajuan_id']) && !empty($params['form']['t_pengajuan_id'])) {
                $modelss = $sql->update("acc_t_pengajuan", ["status" => "terbayar"], ["id" => $params['form']['t_pengajuan_id']]);
            }
        }
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
$app->get('/acc/t_pengeluaran/print', function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    $detail = $db->select("acc_pengeluaran_det.*, acc_m_akun.id as idAkun, acc_m_akun.kode as kodeAkun, acc_m_akun.nama as namaAkun")
            ->from("acc_pengeluaran_det")
            ->join("join", "acc_m_akun", "acc_m_akun.id = acc_pengeluaran_det.m_akun_id")
            ->where("acc_pengeluaran_id", "=", $data['id'])
            ->findAll();
    foreach ($detail as $key => $val) {
        $val->m_akun_id = ["id" => $val->m_akun_id, "kode" => $val->kodeAkun, "nama" => $val->namaAkun];
    }
    $data['tanggalsekarang'] = date("d-m-Y H:i");
    $data['terbilang'] = terbilang($data['total']);
    $data['total'] = (int) $data['total'];
    $data['url_img'] = config('SITE_IMG');
    $setting = getMasterSetting();
    $template = $setting->print_pengeluaran;
    $template = str_replace("{start_detail}", "{%for key, val in detail%}", $template);
    $template = str_replace("{end}", "{%endfor%}", $template);
    $template = str_replace("<pre>", "<pre style='margin:0px'>", $template);
    $view = twigViewPath();
    $content = $view->fetchFromString($template, [
        "data" => $data,
        "detail" => (array) $detail,
    ]);
    $content = str_replace("<p></p>", "", $content);
    echo $content;
    echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
});
/*
 * template print
 */
$app->get("/acc/t_pengeluaran/getTemplate", function ($request, $response) {
    $setting = getMasterSetting();
    return successResponse($response, $setting->print_pengeluaran);
});
$app->post("/acc/t_pengeluaran/saveTemplate", function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    try {
        $model = $db->update("acc_m_setting", $data, ["id" => 1]);
        return successResponse($response, $model);
    } catch (Exception $e) {
        return unprocessResponse($response, ["Terjadi kesalahan pada server"]);
    }
});

$app->post("/acc/t_pengeluaran/savePosition", function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;

    $data['posisi_pengeluaran'] = json_encode($data);
    try {
        $model = $db->update("acc_m_setting", $data, ["id" => 1]);
        return successResponse($response, $model);
    } catch (Exception $e) {
        return unprocessResponse($response, ["Terjadi kesalahan pada server"]);
    }
});
