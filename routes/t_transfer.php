<?php

function validasi($data, $custom = array())
{
    $validasi = array(
        'm_lokasi_id' => 'required',
        'no_transaksi'      => 'required',
        'total'      => 'required',
        'tanggal' => 'required',
        'm_akun_asal_id' => 'required',
        'm_akun_tujuan_id' => 'required',
    );
    GUMP::set_field_name("m_lokasi_id", "Lokasi");
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
    return successResponse($response, [ "kode" => $kode_unit_1 .  "TRN" . date("y"). $no_urut, "urutan" => $no_urut]);
});

$app->get('/acc/t_transfer/akunKas', function ($request, $response){
    $db = $this->db;
    $models = $db->select("*")->from("acc_m_akun")
            ->where("tipe", "=", "Cash & Bank")
            ->where("is_tipe", "=", 0)
            ->where("is_deleted", "=", 0)
            ->findAll();
    
    
    return successResponse($response, [
      'list'        => $models
    ]);
});

$app->get('/acc/t_transfer/index', function ($request, $response) {
    $params = $request->getParams();
    // $sort     = "m_akun.kode ASC";
    $offset   = isset($params['offset']) ? $params['offset'] : 0;
    $limit    = isset($params['limit']) ? $params['limit'] : 20;

    $db = $this->db;
    $db->select("acc_transfer.*, acc_m_lokasi.nama as namaLokasi, acc_m_lokasi.kode as kodeLokasi, acc_m_user.nama as namaUser, akun2.id as idTujuan, akun2.nama as namaTujuan, akun2.kode as kodeTujuan, akun1.id as idAsal, akun1.nama as namaAsal, akun1.kode as kodeAsal")
        ->from("acc_transfer")
        ->join("join", "acc_m_user", "acc_transfer.created_by = acc_m_user.id")
        ->join("join", "acc_m_akun akun1", "acc_transfer.m_akun_asal_id = akun1.id")
        ->join("join", "acc_m_akun akun2", "acc_transfer.m_akun_tujuan_id = akun2.id")
        ->join("join", "acc_m_lokasi", "acc_transfer.m_lokasi_id = acc_m_lokasi.id")
        ->orderBy('acc_transfer.no_urut');
//        ->where("acc_pemasukan.is_deleted", "=", 0);

    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);

        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("acc_transfer.is_deleted", '=', $val);
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
        $models[$key]['m_akun_asal_id'] = ["id" => $val->idAsal, "nama" => $val->namaAsal, "kode" => $val->kodeAsal];
        $models[$key]['m_akun_tujuan_id'] = ["id" => $val->idTujuan, "nama" => $val->namaTujuan, "kode" => $val->kodeTujuan];
        $models[$key]['m_lokasi_id'] = ["id" => $val->m_lokasi_id, "nama" => $val->namaLokasi, "kode" => $val->kodeLokasi];
        
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



$app->post('/acc/t_transfer/create', function ($request, $response) {

    $params = $request->getParams();
    $data   = $params;
//    print_r($data);die();
    $sql = $this->db;
    $validasi = validasi($data['form']);
    if ($validasi === true) {
        $getNoUrut = $sql->select("*")->from("acc_transfer")->orderBy("no_urut DESC")->find();
        $insert['no_urut'] = 1;
        if($getNoUrut){
            $insert['no_urut'] = $getNoUrut->no_urut + 1;
        }
        
        $insert['no_transaksi'] = $data['form']['no_transaksi'];
        $insert['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
        $insert['m_akun_asal_id'] = $data['form']['m_akun_asal_id']['id'];
        $insert['m_akun_tujuan_id'] = $data['form']['m_akun_tujuan_id']['id'];
        $insert['tanggal'] = date("Y-m-d h:i:s",strtotime($data['form']['tanggal']));
        $insert['total'] = $data['form']['total'];
        $insert['keterangan'] = $data['form']['keterangan'];
        $model = $sql->insert("acc_transfer", $insert);
        
        $insert2['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
        $insert2['m_akun_id'] = $data['form']['m_akun_tujuan_id']['id'];
        $insert2['tanggal'] = date("Y-m-d",strtotime($data['form']['tanggal']));
        $insert2['debit'] = $data['form']['total'];
        $insert2['reff_type'] = "Transfer";
        $insert2['reff_id'] = $model->id;
        $model2 = $sql->insert("acc_trans_detail", $insert2);
        
        $insert3['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
        $insert3['m_akun_id'] = $data['form']['m_akun_asal_id']['id'];
        $insert3['tanggal'] = date("Y-m-d",strtotime($data['form']['tanggal']));
        $insert3['kredit'] = $data['form']['total'];
        $insert3['reff_type'] = "Transfer";
        $insert3['reff_id'] = $model->id;
        $model3 = $sql->insert("acc_trans_detail", $insert3);
//        die();
        if ($model && $model2 && $model3) {
            
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, ['Data Gagal Di Simpan']);
        }
    } else {
        return unprocessResponse($response, $validasi);
    }
});

$app->post('/acc/t_transfer/update', function ($request, $response) {

    $data = $request->getParams();
    $sql   = $this->db;
//    print_r($data);die();
    $validasi = validasi($data);

    if ($validasi === true) {
        
        //update acc_pemasukan
        $insert['no_urut'] = $data['form']['no_urut'];
        $insert['no_transaksi'] = $data['form']['no_transaksi'];
        $insert['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
        $insert['m_akun_id'] = $data['form']['m_akun_id']['id'];
        $insert['diterima_dari'] = $data['form']['diterima_dari'];
        $insert['tanggal'] = date("Y-m-d h:i:s",strtotime($data['form']['tanggal']));
        $insert['total'] = $data['form']['total'];
        $model = $sql->update("acc_pemasukan", $insert, ["id" => $data['form']['id']]);
        
        //update acc_trans_detail dari acc_pemasukan
        $insert2['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
        $insert2['m_akun_id'] = $data['form']['m_akun_id']['id'];
        $insert2['tanggal'] = date("Y-m-d",strtotime($data['form']['tanggal']));
        $insert2['debit'] = $data['form']['total'];
        $insert2['reff_type'] = "Penerimaan Header";
        $insert2['reff_id'] = $model->id;
        $model2 = $sql->update("acc_trans_detail", $insert2, ["reff_id"=>$model->id, "reff_type"=>"Penerimaan Header"]);
        
        //select acc_pemasukan_detail lalu hapus acc_pemasukan_detail
        $selectdetail = $sql->select("*")
                        ->from("acc_pemasukan_det")
                        ->where("acc_pemasukan_id", "=", $data['form']['id'])
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
        $delete = $sql->delete("acc_pemasukan_det", ["acc_pemasukan_id" => $data['form']['id']]);
        $deletedetail = $sql->run("DELETE FROM acc_trans_detail WHERE reff_type = 'Penerimaan Detail' AND reff_id IN($array_id) AND m_akun_id IN($array_akun)");
//        $deletedetail = $sql->delete("acc_trans_detail")->where("reff_type", "=", "Penerimaan Detail")
//                        ->customWhere("reff_id IN($array_id)")
//                        ->customWhere("m_akun_id IN($array_akun)");
                         
        
        if ($model && $model2 && $delete && $deletedetail) {
            foreach($data['detail'] as $keys => $vals){
                
                //insert ke acc_pemasukan_det
                $detail['m_akun_id'] = $vals['m_akun_id']['id'];
                $detail['kredit'] = $vals['kredit'];
                $detail['acc_pemasukan_id'] = $model->id;
                $detail['keterangan'] = $vals['keterangan'];
                $modeldetail = $sql->insert("acc_pemasukan_det", $detail);
                
                //insert ke acc_trans_detail
                $detail2['m_akun_id'] = $vals['m_akun_id']['id'];
                $detail2['tanggal'] = date("Y-m-d",strtotime($data['form']['tanggal']));
                $detail2['kredit'] = $vals['kredit'];
                $detail2['reff_type'] = "Penerimaan Detail";
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

$app->post('/acc/t_transfer/trash', function ($request, $response) {

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

    $model = $db->update("t_penerimaan", $data, array('id' => $data['id']));
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});
