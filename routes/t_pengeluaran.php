<?php

date_default_timezone_set('Asia/Jakarta');

$app->post('/acc/t_pengeluaran/upload/{folder}', function ($request, $response) {
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
            $pengeluaran_id = $sql->find("select * from acc_pengeluaran order by id desc");
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


$app->get('/acc/t_pengeluaran/listgambar/{id}', function ($request, $response) {
    $id = $request->getAttribute('id');
    $sql = $this->db;
    $model = $sql->findAll("select * from acc_dokumen_foto where acc_pengeluaran_id={$id}");
    return successResponse($response, $model);
});

$app->post('/acc/t_pengeluaran/removegambar', function ($request, $response) {
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
        'm_akun_id' => 'required',
        'tanggal'      => 'required',
        'total' => 'required',
//        'dibayar_kepada' => 'required',
        'm_supplier_id' => 'required'
    );
    GUMP::set_field_name("m_lokasi_id", "Lokasi");
    GUMP::set_field_name("m_akun_id", "Keluar dari");
//    GUMP::set_field_name("dibayar_kepada", "Dibayar kepada");
    GUMP::set_field_name("total", "Detail");
    GUMP::set_field_name("m_supplier_id", "Supplier");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->get('/acc/t_pengeluaran/kode/{kode}', function ($request, $response) {

    $kode_unit_1 = $request->getAttribute('kode');
    $db = $this->db;

    $model = $db->find("select * from acc_pengeluaran order by id desc");
    $urut = (empty($model)) ? 1 : ((int) substr($model->no_urut, -3)) + 1;
    $no_urut = substr('0000' . $urut, -3);
    return successResponse($response, [ "kode" => $kode_unit_1 . date("y"). "PNGL" . $no_urut, "urutan" => $no_urut]);
});


$app->get('/acc/t_pengeluaran/getDetail', function ($request, $response){
    $params = $request->getParams();
//    print_r($params);die();
    $db = $this->db;
    $models = $db->select("acc_pengeluaran_det.*, acc_m_akun.kode as kodeAkun, acc_m_akun.nama as namaAkun")
            ->from("acc_pengeluaran_det")
            ->join("join", "acc_m_akun", "acc_m_akun.id = acc_pengeluaran_det.m_akun_id")
            ->where("acc_pengeluaran_id", "=", $params['id'])
//            ->where("acc_pemasukan_det.is_deleted", "=", 0)
            ->findAll();
    
    foreach($models as $key => $val){
        $val->m_akun_id = ["id"=>$val->m_akun_id, "kode"=>$val->kodeAkun, "nama"=>$val->namaAkun];
    }
    return successResponse($response, [
      'list'        => $models
    ]);
});

$app->get('/acc/t_pengeluaran/index', function ($request, $response) {
    $params = $request->getParams();
    // $sort     = "m_akun.kode ASC";
    $offset   = isset($params['offset']) ? $params['offset'] : 0;
    $limit    = isset($params['limit']) ? $params['limit'] : 20;

    $db = $this->db;
    $db->select("acc_pengeluaran.*, acc_m_supplier.nama as namaSupplier, acc_m_lokasi.kode as kodeLokasi, acc_m_lokasi.nama as namaLokasi, acc_m_user.nama as namaUser, acc_m_akun.kode as kodeAkun, acc_m_akun.nama as namaAkun")
        ->from("acc_pengeluaran")
        ->join("join", "acc_m_supplier", "acc_pengeluaran.m_supplier_id = acc_m_supplier.id")
        ->join("join", "acc_m_user", "acc_pengeluaran.created_by = acc_m_user.id")
        ->join("join", "acc_m_akun", "acc_pengeluaran.m_akun_id = acc_m_akun.id")
        ->join("join", "acc_m_lokasi", "acc_m_lokasi.id = acc_pengeluaran.m_lokasi_id")
        ->orderBy('acc_pengeluaran.tanggal DESC')
        ->orderBy('acc_pengeluaran.created_at DESC');
//        ->where("acc_pemasukan.is_deleted", "=", 0);

    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);

        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("acc_pengeluaran.is_deleted", '=', $val);
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
        $models[$key]['tanggal'] = date("d-m-Y h:i:s",strtotime($val->tanggal));
        $models[$key]['created_at'] = date("d-m-Y h:i:s",$val->created_at);
        $models[$key]['m_akun_id'] = ["id" => $val->m_akun_id, "nama" => $val->namaAkun, "kode" => $val->kodeAkun];
        $models[$key]['m_lokasi_id'] = ["id" => $val->m_lokasi_id, "nama" => $val->namaLokasi, "kode" => $val->kodeLokasi];
        $models[$key]['m_supplier_id'] = ["id" => $val->m_supplier_id, "nama" => $val->namaSupplier];
    }
//     print_r($models);exit();
//    die();
//      print_r($arr);exit();
    return successResponse($response, [
      'list'        => $models,
      'totalItems'  => $totalItem,
      'base_url'    => str_replace('api/', '', config('SITE_URL'))
    ]);
});



$app->post('/acc/t_pengeluaran/create', function ($request, $response) {

    $params = $request->getParams();
    $data   = $params;
//    print_r($data);die();
    $sql = $this->db;
    $validasi = validasi($data['form']);
    if ($validasi === true) {
        $getNoUrut = $sql->select("*")->from("acc_pengeluaran")->orderBy("no_urut DESC")->find();
        $insert['no_urut'] = 1;
        if($getNoUrut){
            $insert['no_urut'] = $getNoUrut->no_urut + 1;
        }
        
        $insert['no_transaksi'] = $data['form']['no_transaksi'];
        $insert['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
        $insert['m_akun_id'] = $data['form']['m_akun_id']['id'];
        $insert['m_supplier_id'] = $data['form']['m_supplier_id']['id'];
        $insert['dibayar_kepada'] = (isset($data['form']['dibayar_kepada']) && !empty($data['form']['dibayar_kepada']) ? $data['form']['dibayar_kepada'] : '');
        $insert['tanggal'] = date("Y-m-d h:i:s",strtotime($data['form']['tanggal']));
        $insert['total'] = $data['form']['total'];
        $model = $sql->insert("acc_pengeluaran", $insert);
        
        $insert2['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
        $insert2['m_akun_id'] = $data['form']['m_akun_id']['id'];
        $insert2['m_supplier_id'] = $data['form']['m_supplier_id']['id'];
        $insert2['tanggal'] = date("Y-m-d",strtotime($data['form']['tanggal']));
        $insert2['kredit'] = $data['form']['total'];
        $insert2['kode'] = $data['form']['no_transaksi'];
        $insert2['reff_type'] = "Pengeluaran Header";
        $insert2['reff_id'] = $model->id;
        $model2 = $sql->insert("acc_trans_detail", $insert2);
//        die();
        if ($model && $model2) {
            foreach($data['detail'] as $key => $val){
                $detail['m_akun_id'] = $val['m_akun_id']['id'];
                $detail['debit'] = $val['debit'];
                $detail['acc_pengeluaran_id'] = $model->id;
                $detail['keterangan'] = (isset($val['keterangan']) && !empty($val['keterangan']) ? $val['keterangan'] : '');
                $modeldetail = $sql->insert("acc_pengeluaran_det", $detail);
                
                $detail2['m_akun_id'] = $val['m_akun_id']['id'];
                $detail2['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
                $detail2['tanggal'] = date("Y-m-d",strtotime($data['form']['tanggal']));
                $detail2['debit'] = $val['debit'];
                $detail2['keterangan'] = (isset($val['keterangan']) && !empty($val['keterangan']) ? $val['keterangan'] : '');
                $detail2['reff_type'] = "Pengeluaran Detail";
                $detail2['reff_id'] = $modeldetail->id;
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

$app->post('/acc/t_pengeluaran/update', function ($request, $response) {

    $data = $request->getParams();
    $sql   = $this->db;
//    print_r($data);die();
    $validasi = validasi($data['form']);

    if ($validasi === true) {
        
        //update acc_pemasukan
        $insert['no_urut'] = $data['form']['no_urut'];
        $insert['no_transaksi'] = $data['form']['no_transaksi'];
        $insert['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
        $insert['m_supplier_id'] = $data['form']['m_supplier_id']['id'];
        $insert['m_akun_id'] = $data['form']['m_akun_id']['id'];
        $insert['dibayar_kepada'] = (isset($data['form']['dibayar_kepada']) && !empty($data['form']['dibayar_kepada']) ? $data['form']['dibayar_kepada'] : '');
        $insert['tanggal'] = date("Y-m-d h:i:s",strtotime($data['form']['tanggal']));
        $insert['total'] = $data['form']['total'];
        $model = $sql->update("acc_pengeluaran", $insert, ["id" => $data['form']['id']]);
        
        //update acc_trans_detail dari acc_pemasukan
        $insert2['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
        $insert2['m_akun_id'] = $data['form']['m_akun_id']['id'];
        $insert2['tanggal'] = date("Y-m-d",strtotime($data['form']['tanggal']));
        $insert2['kredit'] = $data['form']['total'];
        $insert2['m_supplier_id'] = $data['form']['m_supplier_id']['id'];
        $insert2['kode'] = $data['form']['no_transaksi'];
        $insert2['reff_type'] = "Pengeluaran Header";
        $insert2['reff_id'] = $model->id;
        $model2 = $sql->update("acc_trans_detail", $insert2, ["reff_id"=>$model->id, "reff_type"=>"Pengeluaran Header"]);
        
        //select acc_pemasukan_detail lalu hapus acc_pemasukan_detail
        $selectdetail = $sql->select("*")
                        ->from("acc_pengeluaran_det")
                        ->where("acc_pengeluaran_id", "=", $data['form']['id'])
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
        
        //hapus acc_pemasukan_det, acc_trans_detail dari detail
        $delete = $sql->delete("acc_pengeluaran_det", ["acc_pengeluaran_id" => $data['form']['id']]);
        $deletedetail = $sql->run("DELETE FROM acc_trans_detail WHERE reff_type = 'Pengeluaran Detail' AND reff_id IN($array_id) AND m_akun_id IN($array_akun)");
//        $deletedetail = $sql->delete("acc_trans_detail")->where("reff_type", "=", "Penerimaan Detail")
//                        ->customWhere("reff_id IN($array_id)")
//                        ->customWhere("m_akun_id IN($array_akun)");
                         
        
        if ($model && $model2 && $delete && $deletedetail) {
            foreach($data['detail'] as $keys => $vals){
                
                //insert ke acc_pemasukan_det
                $detail['m_akun_id'] = $vals['m_akun_id']['id'];
                $detail['debit'] = $vals['debit'];
                $detail['acc_pengeluaran_id'] = $model->id;
                $detail['keterangan'] = (isset($val['keterangan']) && !empty($val['keterangan']) ? $val['keterangan'] : '');
                $modeldetail = $sql->insert("acc_pengeluaran_det", $detail);
                
                //insert ke acc_trans_detail
                $detail2['m_akun_id'] = $vals['m_akun_id']['id'];
                $detail2['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
                $detail2['tanggal'] = date("Y-m-d",strtotime($data['form']['tanggal']));
                $detail2['debit'] = $vals['debit'];
                $detail2['reff_type'] = "Pengeluaran Detail";
                $detail2['keterangan'] = (isset($val['keterangan']) && !empty($val['keterangan']) ? $val['keterangan'] : '');
                $detail2['reff_id'] = $modeldetail->id;
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

$app->post('/acc/t_pengeluaran/trash', function ($request, $response) {

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

    $model = $db->update("t_pengeluaran", $data, array('id' => $data['id']));
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});
