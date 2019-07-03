<?php

$app->post('/acc/t_jurnal_umum/upload/{folder}', function ($request, $response) {
    $folder = $request->getAttribute('folder');
    $params = $request->getParams();

    if (!empty($_FILES)) {
        $tempPath = $_FILES['file']['tmp_name'];
        $sql = $this->db;
        $id_dokumen = $sql->find("select * from acc_dokumen_foto order by id desc");
        $gid = (isset($id_dokumen->id)) ? $id_dokumen->id + 1 : 1;
        $newName = $gid . "_" . urlParsing($_FILES['file']['name']);
        $uploadPath = "acc/landaacc/upload/" . $folder . DIRECTORY_SEPARATOR . $newName;

        move_uploaded_file($tempPath, $uploadPath);

        if ($params['id'] == "undefined" || empty($params['id'])) {
            $pengeluaran_id = $sql->find("select * from acc_jurnal order by id desc");
            $pid = (isset($pengeluaran_id->id)) ? $pengeluaran_id->id + 1 : 1;
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


$app->get('/acc/t_jurnal_umum/listgambar/{id}', function ($request, $response) {
    $id = $request->getAttribute('id');
    $sql = $this->db;
    $model = $sql->findAll("select * from acc_dokumen_foto where acc_jurnal_id={$id}");
    return successResponse($response, $model);
});

$app->post('/acc/t_jurnal_umum/removegambar', function ($request, $response) {
    $params = $request->getParams();
    $sql = $this->db;

    $delete = $sql->delete('acc_dokumen_foto', array('id' => $params['id'], "img" => $params['img']));
    unlink(__DIR__ . "/../upload/bukti/" . $params['img']);
});


function validasi($data, $custom = array())
{
    $validasi = array(
        'no_transaksi' => 'required',
        'm_lokasi_id'      => 'required',
        'tanggal'      => 'required',
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

$app->get('/acc/t_jurnal_umum/kode/{kode}', function ($request, $response) {

    $kode_unit_1 = $request->getAttribute('kode');
    $db = $this->db;

    $model = $db->find("select * from acc_jurnal order by id desc");
    $urut = (empty($model)) ? 1 : ((int) substr($model->no_urut, -3)) + 1;
    $no_urut = substr('0000' . $urut, -3);
    return successResponse($response, [ "kode" => $kode_unit_1 . date("y") . "JRNL" . $no_urut, "urutan" => $no_urut]);
});


$app->get('/acc/t_jurnal_umum/getDetail', function ($request, $response){
    $params = $request->getParams();
//    print_r($params);die();
    $db = $this->db;
    $models = $db->select("acc_jurnal_det.*, acc_m_akun.kode as kodeAkun, acc_m_akun.nama as namaAkun")
            ->from("acc_jurnal_det")
            ->join("join", "acc_m_akun", "acc_m_akun.id = acc_jurnal_det.m_akun_id")
            ->where("acc_jurnal_id", "=", $params['id'])
//            ->where("acc_pemasukan_det.is_deleted", "=", 0)
            ->findAll();
    
    foreach($models as $key => $val){
        $val->m_akun_id = ["id"=>$val->m_akun_id, "kode"=>$val->kodeAkun, "nama"=>$val->namaAkun];
    }
    return successResponse($response, [
      'list'        => $models
    ]);
});

$app->get('/acc/t_jurnal_umum/index', function ($request, $response) {
    $params = $request->getParams();
    // $sort     = "m_akun.kode ASC";
    $offset   = isset($params['offset']) ? $params['offset'] : 0;
    $limit    = isset($params['limit']) ? $params['limit'] : 20;

    $db = $this->db;
    $db->select("acc_jurnal.*, acc_m_lokasi.id as idLokasi, acc_m_lokasi.kode as kodeLokasi, acc_m_lokasi.nama as namaLokasi, acc_m_user.nama as namaUser")
        ->from("acc_jurnal")
        ->join("join", "acc_m_user", "acc_jurnal.created_by = acc_m_user.id")
        ->join("join", "acc_m_lokasi", "acc_m_lokasi.id = acc_jurnal.m_lokasi_id")
        ->orderBy('acc_jurnal.no_urut');
//        ->where("acc_pemasukan.is_deleted", "=", 0);

    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);

        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("acc_jurnal.is_deleted", '=', $val);
            }else{
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

    $models    = $db->findAll();
    $totalItem = $db->count();
    
    foreach($models as $key => $val){
        $models[$key] = (array) $val;
        $models[$key]['tanggal'] = date("d-m-Y h:i:s", strtotime($val->tanggal));
        $models[$key]['created_at'] = date("d-m-Y h:i:s",$val->created_at);
        $models[$key]['m_lokasi_id'] = ["id"=>$val->idLokasi, "kode"=>$val->kodeLokasi, "nama"=>$val->namaLokasi];
    }
    
    return successResponse($response, [
      'list'        => $models,
      'totalItems'  => $totalItem,
      'base_url'    => str_replace('api/', '', config('SITE_URL'))
    ]);
});



$app->post('/acc/t_jurnal_umum/create', function ($request, $response) {

    $params = $request->getParams();
    $data   = $params;
//    print_r($data);die();
    $sql = $this->db;
    $validasi = validasi($data['form']);
    if ($validasi === true) {
        $getNoUrut = $sql->select("*")->from("acc_jurnal")->orderBy("no_urut DESC")->find();
        $insert['no_urut'] = 1;
        if($getNoUrut){
            $insert['no_urut'] = $getNoUrut->no_urut + 1;
        }
        
        $insert['no_transaksi'] = $data['form']['no_transaksi'];
        $insert['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
        $insert['keterangan'] = (isset($data['form']['keterangan']) && !empty($data['form']['keterangan']) ? $data['form']['keterangan'] : $data['form']['keterangan']);
        $insert['tanggal'] = date("Y-m-d h:i:s",strtotime($data['form']['tanggal']));
        $insert['total_kredit'] = $data['form']['total_kredit'];
        $insert['total_debit'] = $data['form']['total_debit'];
        $model = $sql->insert("acc_jurnal", $insert);
        
        $insert2['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
        $insert2['tanggal'] = date("Y-m-d",strtotime($data['form']['tanggal']));
        $insert2['debit'] = $data['form']['total_debit'];
        $insert2['kredit'] = $data['form']['total_kredit'];
        $insert2['reff_type'] = "Jurnal Header";
        $insert2['reff_id'] = $model->id;
        $insert2['kode'] = $data['form']['no_transaksi'];
        $insert2['keterangan'] = (isset($data['form']['keterangan']) && !empty($data['form']['keterangan']) ? $data['form']['keterangan'] : $data['form']['keterangan']);
        $model2 = $sql->insert("acc_trans_detail", $insert2);
//        die();
        if ($model && $model2) {
            foreach($data['detail'] as $key => $val){
                $detail['m_akun_id'] = $val['m_akun_id']['id'];
                $detail['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
                $detail['kredit'] = $val['kredit'];
                $detail['debit'] = $val['debit'];
                $detail['acc_jurnal_id'] = $model->id;
                $detail['keterangan'] = (isset($val['keterangan']) && !empty($val['keterangan'])? $val['keterangan'] : $val['keterangan']);
                $modeldetail = $sql->insert("acc_jurnal_det", $detail);
                
                $detail2['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
                $detail2['m_akun_id'] = $val['m_akun_id']['id'];
                $detail2['tanggal'] = date("Y-m-d",strtotime($data['form']['tanggal']));
                $detail2['kredit'] = $val['kredit'];
                $detail2['debit'] = $val['debit'];
                $detail2['reff_type'] = "Jurnal Detail";
                $detail2['reff_id'] = $modeldetail->id;
                $detail2['keterangan'] = (isset($val['keterangan']) && !empty($val['keterangan'])? $val['keterangan'] : $val['keterangan']);
                $modeldetail2 = $sql->insert("acc_trans_detail", $detail2);
            }
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, ['Data Gagal Di Simpan']);
        }
    } else {
        return unprocessResponse($response, $validasi);
    }
});

$app->post('/acc/t_jurnal_umum/update', function ($request, $response) {

    $data = $request->getParams();
    $sql   = $this->db;
//    print_r($data);die();
    $validasi = validasi($data);

    if ($validasi === true) {
        
        //update acc_pemasukan
        $insert['no_urut'] = $data['form']['no_urut'];
        $insert['no_transaksi'] = $data['form']['no_transaksi'];
        $insert['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
        $insert['keterangan'] = $data['form']['keterangan'];
        $insert['tanggal'] = date("Y-m-d h:i:s",strtotime($data['form']['tanggal']));
        $insert['total_kredit'] = $data['form']['total_kredit'];
        $insert['total_debit'] = $data['form']['total_debit'];
        $model = $sql->update("acc_jurnal", $insert, ["id" => $data['form']['id']]);
        
        //update acc_trans_detail dari acc_jurnal
        $insert2['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
        $insert2['tanggal'] = date("Y-m-d",strtotime($data['form']['tanggal']));
        $insert2['debit'] = $data['form']['total_debit'];
        $insert2['kredit'] = $data['form']['total_kredit'];
        $insert2['reff_type'] = "Jurnal Header";
        $insert2['keterangan'] = $data['form']['keterangan'];
        $insert2['reff_id'] = $model->id;
        $model2 = $sql->update("acc_trans_detail", $insert2, ["reff_id"=>$model->id, "reff_type"=>"Jurnal Header"]);
        
        //select acc_jurnal_detail lalu hapus acc_jurnal_detail
        $selectdetail = $sql->select("*")
                        ->from("acc_jurnal_det")
                        ->where("acc_jurnal_id", "=", $data['form']['id'])
                        ->findAll();
        $array_id = [];
        $array_akun = [];
        foreach($selectdetail as $key => $val){
            array_push($array_id, $val->id);
            array_push($array_akun, $val->m_akun_id);
        }
        $array_id = implode(", ", $array_id);
        $array_akun = implode(", ", $array_akun);
//        echo $array_akun;
//        echo $array_id;
//        die();
        
        //hapus acc_jurnal_det, acc_trans_detail dari detail
        $delete = $sql->delete("acc_jurnal_det", ["acc_jurnal_id" => $data['form']['id']]);
        $deletedetail = $sql->run("DELETE FROM acc_trans_detail WHERE reff_type = 'Jurnal Detail' AND reff_id IN($array_id) AND m_akun_id IN($array_akun)");
                         
        
        if ($model && $model2 && $delete && $deletedetail) {
            foreach($data['detail'] as $keys => $vals){
                
                $detail['m_akun_id'] = $vals['m_akun_id']['id'];
                $detail['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
                $detail['kredit'] = $vals['kredit'];
                $detail['debit'] = $vals['debit'];
                $detail['acc_jurnal_id'] = $model->id;
                $detail['keterangan'] = $vals['keterangan'];
                $modeldetail = $sql->insert("acc_jurnal_det", $detail);
                
                $detail2['m_akun_id'] = $vals['m_akun_id']['id'];
                $detail2['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
                $detail2['tanggal'] = date("Y-m-d",strtotime($data['form']['tanggal']));
                $detail2['kredit'] = $vals['kredit'];
                $detail2['debit'] = $vals['debit'];
                $detail2['reff_type'] = "Jurnal Detail";
                $detail2['reff_id'] = $modeldetail->id;
                $detail2['keterangan'] = $vals['keterangan'];
                $modeldetail2 = $sql->insert("acc_trans_detail", $detail2);
                
            }
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, ['Data Gagal Di Simpan']);
        }
    } else {
        return unprocessResponse($response, $validasi);
    }
});

$app->post('/acc/t_jurnal_umum/trash', function ($request, $response) {

    $data = $request->getParams();
    $db   = $this->db;

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

    $model = $db->update("t_jurnal_umum", $data, array('id' => $data['id']));
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});
