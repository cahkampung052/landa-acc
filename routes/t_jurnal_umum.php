<?php

function validasi($data, $custom = array()) {
    $validasi = array(
        'm_lokasi_id' => 'required',
        'tanggal' => 'required',
        'total_kredit' => 'required',
        'total_debit' => 'required'
    );
    GUMP::set_field_name("no_transaksi", "No transaksi");
    GUMP::set_field_name("m_lokasi_id", "Lokasi");
    GUMP::set_field_name("total_kredit", "Total Kredit");
    GUMP::set_field_name("total_debit", "Total Debit");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

/*
 * upload gambar
 */
$app->post('/acc/t_jurnal_umum/upload/{folder}', function ($request, $response) {
    $folder = $request->getAttribute('folder');
    $params = $request->getParams();

    if (!empty($_FILES)) {
        $tempPath = $_FILES['file']['tmp_name'];
        $sql = $this->db;
        $id_dokumen = $sql->find("select * from acc_dokumen_foto order by id desc");
        $gid = (isset($id_dokumen->id)) ? $id_dokumen->id + 1 : 1;
        $newName = $gid . "_" . urlParsing($_FILES['file']['name']);
        $uploadPath = "file/jurnal-umum/" . date('Y') . "/" . str_replace("0", "", date("m"));
//        echo $uploadPath;die();
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }
        move_uploaded_file($tempPath, $uploadPath . DIRECTORY_SEPARATOR . $newName);

        if ($params['id'] == "undefined" || empty($params['id'])) {
            $pengeluaran_id = $sql->find("select * from acc_jurnal order by id desc");
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
                    'acc_jurnal_id' => $pid,
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
 * ambil list gambar
 */
$app->get('/acc/t_jurnal_umum/listgambar/{id}', function ($request, $response) {
    $id = $request->getAttribute('id');
    $sql = $this->db;
    $model = $sql->findAll("select * from acc_dokumen_foto where acc_jurnal_id=$id");
    return successResponse($response, ["model" => $model, "url" => "api/file/jurnal-umum/" . date("Y") . "/" . str_replace("0", "", date("m")) . "/"]);
});

/*
 * delete gambar
 */
$app->post('/acc/t_jurnal_umum/removegambar', function ($request, $response) {
    $params = $request->getParams();
    $sql = $this->db;

    $delete = $sql->delete('acc_dokumen_foto', array('id' => $params['id'], "img" => $params['img']));
    unlink(__DIR__ . "/../../../file/jurnal-umum/" . date('Y') . "/" . str_replace("0", "", date("m")) . "/" . $params['img']);
});


/*
 * ambil / generate kode
 */
$app->get('/acc/t_jurnal_umum/kode/{kode}', function ($request, $response) {

    $kode_unit_1 = $request->getAttribute('kode');
    $db = $this->db;

    $model = $db->find("select * from acc_jurnal order by id desc");
    $urut = (empty($model)) ? 1 : ((int) substr($model->no_urut, -3)) + 1;
    $no_urut = substr('0000' . $urut, -3);
    return successResponse($response, ["kode" => $kode_unit_1 . date("y") . "JRNL" . $no_urut, "urutan" => $no_urut]);
});

/*
 * ambil list detail
 */
$app->get('/acc/t_jurnal_umum/getDetail', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    $models = $db->select("acc_jurnal_det.*, acc_m_akun.kode as kodeAkun, acc_m_akun.nama as namaAkun, acc_m_lokasi.kode as kodeLokasi, acc_m_lokasi.nama as namaLokasi")
            ->from("acc_jurnal_det")
            ->join("join", "acc_m_akun", "acc_m_akun.id = acc_jurnal_det.m_akun_id")
            ->join("join", "acc_m_lokasi", "acc_m_lokasi.id = acc_jurnal_det.m_lokasi_id")
            ->where("acc_jurnal_id", "=", $params['id'])
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
 * ambil riwayat jurnal umum
 */
$app->get('/acc/t_jurnal_umum/index', function ($request, $response) {
    $params = $request->getParams();
    $tableuser = tableUser();
    // $sort     = "m_akun.kode ASC";
    $offset = isset($params['offset']) ? $params['offset'] : 0;
    $limit = isset($params['limit']) ? $params['limit'] : 20;

    $db = $this->db;
    $db->select("acc_jurnal.*, acc_m_lokasi.id as idLokasi, acc_m_lokasi.kode as kodeLokasi, acc_m_lokasi.nama as namaLokasi")
            ->from("acc_jurnal")
//            ->join("join", $tableuser, $tableuser.".id = acc_jurnal.created_by")
            ->join("join", "acc_m_lokasi", "acc_m_lokasi.id = acc_jurnal.m_lokasi_id")
            ->orderBy('acc_jurnal.tanggal DESC')
            ->orderBy('acc_jurnal.created_at DESC');

    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);

        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("acc_jurnal.is_deleted", '=', $val);
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
        $models[$key]['tanggal_formated'] = date("d-m-Y h:i", strtotime($val->tanggal));
        $models[$key]['created_at'] = date("d-m-Y h:i", $val->created_at);
        $models[$key]['m_lokasi_id'] = ["id" => $val->idLokasi, "kode" => $val->kodeLokasi, "nama" => $val->namaLokasi];
        $models[$key]['status'] = ucfirst($val->status);
        
    }

    return successResponse($response, [
        'list' => $models,
        'totalItems' => $totalItem,
        'base_url' => str_replace('api/', '', config('SITE_URL'))
    ]);
});


/*
 * simpan jurnal umum
 */
$app->post('/acc/t_jurnal_umum/save', function ($request, $response) {

    $params = $request->getParams();
    $sql = $this->db;
    $validasi = validasi($params['form']);
    if ($validasi === true) {
        /**
         * Generate kode jurnal
         */
        $get_bulan = date("m", strtotime($params['form']['tanggal']));
        $get_tahun = date("Y", strtotime($params['form']['tanggal']));
        $kode = generateNoTransaksi("jurnal", $params['form']['m_lokasi_id']['kode'], "JM", $get_bulan, $get_tahun);
//        print_r($kode);die();
        $jurnal['no_urut'] = (empty($kode)) ? 1 : ((int) substr($kode, -5));
        
        /**
         * Simpan jurnal
         */
        $jurnal['m_lokasi_id'] = $params['form']['m_lokasi_id']['id'];
        $jurnal['m_lokasi_jurnal_id'] = $jurnal['m_lokasi_id'];
        
        $jurnal['tanggal'] = date("Y-m-d h:i:s", strtotime($params['form']['tanggal']));
        $jurnal['total_debit'] = $params['form']['total_debit'];
        $jurnal['total_kredit'] = $params['form']['total_kredit'];
        $jurnal['status'] = $params['form']['status'];

        foreach ($params['detail'] as $key => $value) {
            $keterangan[$key] = $value['keterangan'];
        }
        $keteranganJurnal = join("<br>",$keterangan);
        $jurnal['keterangan'] = (isset($keteranganJurnal) && !empty($keteranganJurnal) ? $keteranganJurnal : NULL);
        if (isset($params['form']['id']) && !empty($params['form']['id'])) {
            $jurnal['no_urut'] = $params['form']['no_urut'];
            $jurnal['no_transaksi'] = $params['form']['no_transaksi'];
            $model = $sql->update("acc_jurnal", $jurnal, ["id" => $params['form']['id']]);
            /**
             * Hapus jurnal detail
             */
            $sql->delete("acc_jurnal_det", ["acc_jurnal_id" => $model->id]);
            /**
             * Hapus trans detail
             */
            $sql->delete("acc_trans_detail", ["reff_type"=> "acc_jurnal", "reff_id" => $model->id]);
        } else {
            $jurnal['no_transaksi'] = $kode;
            $model = $sql->insert("acc_jurnal", $jurnal);
        }
       
        /**
         * Simpan ke pemasukan detail
         */
        if (isset($params['detail']) && !empty($params['detail'])) {
            foreach ($params['detail'] as $key => $val) {
                $detail['m_akun_id'] = $val['m_akun_id']['id'];
                $detail['m_lokasi_id'] = $model->m_lokasi_id;
                $detail['kredit'] = $val['kredit'];
                $detail['debit'] = $val['debit'];
                $detail['acc_jurnal_id'] = $model->id;
                $detail['keterangan'] = (isset($val['keterangan']) && !empty($val['keterangan']) ? $val['keterangan'] : '');
                $modeldetail = $sql->insert("acc_jurnal_det", $detail);
                
                /**
                 * Simpan trans detail ke array
                 */
                $transDetail[$key]['m_akun_id'] = $modeldetail->m_akun_id;
                $transDetail[$key]['m_lokasi_id'] = $modeldetail->m_lokasi_id;
                $transDetail[$key]['tanggal'] = date("Y-m-d", strtotime($model->tanggal));
                $transDetail[$key]['kredit'] = $modeldetail->kredit;
                $transDetail[$key]['debit'] = $modeldetail->debit;
                $transDetail[$key]['keterangan'] = $modeldetail->keterangan;
                $transDetail[$key]['kode'] = $model->no_transaksi;
                $transDetail[$key]['reff_type'] = "acc_jurnal";
                $transDetail[$key]['reff_id'] = $model->id;
            }
        }
        /**
         * Simpan array trans detail ke database jika simpan dan kunci
         */
        if($params['form']['status'] == "terposting"){
            insertTransDetail($transDetail);
        }
        
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, $validasi);
    }
});

/*
 * hapus jurnal umum
 */
$app->post('/acc/t_jurnal_umum/delete', function ($request, $response) {

    $data = $request->getParams();
    $db = $this->db;
    $model = $db->delete("acc_jurnal", ['id' => $data['id']]);
    $model = $db->delete("acc_jurnal_det", ['acc_jurnal_id' => $data['id']]);
    $model = $db->delete("acc_trans_detail", ['reff_type' => 'acc_jurnal','reff_id' => $data['id']]);
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});

$app->get('/acc/t_jurnal_umum/print', function ($request, $response) {
    $data = $request->getParams();
//    print_r($data);die();
    $db = $this->db;
    $detail = $db->select("acc_jurnal_det.*, acc_m_akun.id as idAkun, acc_m_akun.kode as kodeAkun, acc_m_akun.nama as namaAkun")
            ->from("acc_jurnal_det")
            ->join("join", "acc_m_akun", "acc_m_akun.id = acc_jurnal_det.m_akun_id")
            ->where("acc_jurnal_id", "=", $data['id'])
            ->findAll();
    
    foreach($detail as $key => $val){
        $val->m_akun_id = ["id"=>$val->m_akun_id, "kode"=>$val->kodeAkun, "nama"=>$val->namaAkun];
    }
    
    $data['tanggalsekarang'] = date("d-m-Y H:i");
    $a = getMasterSetting();
    $template = $a->print_jurnal;
    $template = str_replace("<tr><td>{start_detail}</td></tr>", "{%for key, val in detail%}", $template);
    $template = str_replace("<tr><td>{end}</td></tr>", "{%endfor%}", $template);
//    echo json_encode($data);die();
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
$app->get("/acc/t_jurnal_umum/getTemplate", function ($request, $response){
    $a = getMasterSetting();
    return successResponse($response, $a->print_jurnal);
});

$app->post("/acc/t_jurnal_umum/saveTemplate", function ($request, $response){
    $data = $request->getParams();
    $db = $this->db;
    
    try{
        $model = $db->update("acc_m_setting", $data, ["id"=>1]);
       return successResponse($response, $model);
    } catch (Exception $e) {
        return unprocessResponse($response, ["Terjadi kesalahan pada server"]);
    } 
});
