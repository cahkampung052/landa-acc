<?php

date_default_timezone_set('Asia/Jakarta');

function validasi($data, $custom = array())
{
    $validasi = array(
        'm_lokasi_asal_id' => 'required',
        'm_lokasi_tujuan_id' => 'required',
//        'no_transaksi'      => 'required',
        'total'      => 'required',
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
    $db->select("acc_transfer.*, lok1.nama as namaLokAsal, lok1.kode as kodeLokAsal, lok2.nama as namaLokTujuan, lok2.kode as kodeLokTujuan, acc_m_user.nama as namaUser, akun2.id as idTujuan, akun2.nama as namaTujuan, akun2.kode as kodeTujuan, akun1.id as idAsal, akun1.nama as namaAsal, akun1.kode as kodeAsal")
        ->from("acc_transfer")
        ->join("join", "acc_m_user", "acc_transfer.created_by = acc_m_user.id")
        ->join("join", "acc_m_akun akun1", "acc_transfer.m_akun_asal_id = akun1.id")
        ->join("join", "acc_m_akun akun2", "acc_transfer.m_akun_tujuan_id = akun2.id")
        ->join("join", "acc_m_lokasi lok1", "acc_transfer.m_lokasi_asal_id = lok1.id")
            ->join("join", "acc_m_lokasi lok2", "acc_transfer.m_lokasi_tujuan_id = lok2.id")
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
        $models[$key]['tanggal'] = date("Y-m-d h:i:s", strtotime($val->tanggal));
        $models[$key]['tanggal_formated'] = date("d-m-Y h:i:s", strtotime($val->tanggal));
        $models[$key]['created_at'] = date("d-m-Y h:i:s",$val->created_at);
        $models[$key]['m_akun_asal_id'] = ["id" => $val->idAsal, "nama" => $val->namaAsal, "kode" => $val->kodeAsal];
        $models[$key]['m_akun_tujuan_id'] = ["id" => $val->idTujuan, "nama" => $val->namaTujuan, "kode" => $val->kodeTujuan];
        $models[$key]['m_lokasi_asal_id'] = ["id" => $val->m_lokasi_asal_id, "nama" => $val->namaLokAsal, "kode" => $val->kodeLokAsal];
        $models[$key]['m_lokasi_tujuan_id'] = ["id" => $val->m_lokasi_tujuan_id, "nama" => $val->namaLokTujuan, "kode" => $val->kodeLokTujuan];
        
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


$app->post('/acc/t_transfer/save', function ($request, $response) {

    $params = $request->getParams();
    $data = $params;
//    print_r($data);die();
    $sql = $this->db;
    $validasi = validasi($data['form']);
    if ($validasi === true) {
        
        /*
        * akun pengimbang
        */
        $getakun = $sql->select("*")->from("acc_m_akun_peta")->where("type", "=", "Pengimbang Neraca")->find();

        $getNoUrut = $sql->select("*")->from("acc_transfer")->orderBy("no_urut DESC")->find();
        $insert['no_urut'] = 1;
        $urut = 1;
//        print_r($getNoUrut);die();
        if ($getNoUrut) {
            $insert['no_urut'] = $getNoUrut->no_urut + 1;
            $urut = ((int) substr($getNoUrut->no_urut, -4)) + 1;
        }
        $no_urut = substr('0000' . $urut, -4);
        $kode = $data['form']['m_lokasi_asal_id']['kode'] . date("y") . "TRNS" . $no_urut;

//        $insert['no_transaksi'] = $data['form']['no_transaksi'];
        $insert['m_lokasi_asal_id'] = $data['form']['m_lokasi_asal_id']['id'];
        $insert['m_lokasi_tujuan_id'] = $data['form']['m_lokasi_tujuan_id']['id'];
        $insert['m_akun_asal_id'] = $data['form']['m_akun_asal_id']['id'];
        $insert['m_akun_tujuan_id'] = $data['form']['m_akun_tujuan_id']['id'];
        $insert['tanggal'] = date("Y-m-d h:i:s",strtotime($data['form']['tanggal']));
        $insert['total'] = $data['form']['total'];
        $insert['keterangan'] = (isset($data['form']['keterangan']) && !empty($data['form']['keterangan']) ? $data['form']['keterangan'] : '');
//        print_r($insert);die();
        if (isset($data['form']['id']) && !empty($data['form']['id'])) {
            $insert['no_urut'] = $data['form']['no_urut'];
            $insert['no_transaksi'] = $data['form']['no_transaksi'];
            $model = $sql->update("acc_transfer", $insert, ["id" => $data['form']['id']]);
        } else {
            $insert['no_transaksi'] = $kode;
            $model = $sql->insert("acc_transfer", $insert);
        }
        
        //delete transdetail
        $deletetransdetail = $sql->delete("acc_trans_detail", ["reff_id"=>$model->id, "reff_type"=>"acc_transfer"]);

        /*
         * deklarasi untuk simpan ke transdetail
         */
        $index = 0;
        $transDetail = [];
        
        $insert2['m_lokasi_id'] = $data['form']['m_lokasi_tujuan_id']['id'];
        $insert2['m_akun_id'] = $data['form']['m_akun_tujuan_id']['id'];
        $insert2['tanggal'] = date("Y-m-d",strtotime($data['form']['tanggal']));
        $insert2['debit'] = $data['form']['total'];
        $insert2['reff_type'] = "acc_transfer";
        $insert2['reff_id'] = $model->id;
        $insert2['keterangan'] = (isset($data['form']['keterangan']) && !empty($data['form']['keterangan']) ? $data['form']['keterangan'] : '');
        $insert2['kode'] = $model->no_transaksi;
        
        $transDetail[$index] = $insert2;
        
        /*
         * jika lokasi beda
         */
        if($data['form']['m_lokasi_asal_id'] != $data['form']['m_lokasi_tujuan_id']){
            
            
            $insert2_ = $insert2;
            $insert2_['m_akun_id'] = $getakun->m_akun_id;
            $insert2_['debit'] = NULL;
            $insert2_['kredit'] = $data['form']['total'];
            
            $transDetail[$index+1] = $insert2_;
            $index = $index+2;
        }else{
            $index = $index+1;
        }
        
        $insert3['m_lokasi_id'] = $data['form']['m_lokasi_asal_id']['id'];
        $insert3['m_akun_id'] = $data['form']['m_akun_asal_id']['id'];
        $insert3['tanggal'] = date("Y-m-d",strtotime($data['form']['tanggal']));
        $insert3['kredit'] = $data['form']['total'];
        $insert3['reff_type'] = "acc_transfer";
        $insert3['reff_id'] = $model->id;
        $insert3['kode'] = $model->no_transaksi;
        $insert3['keterangan'] = (isset($data['form']['keterangan']) && !empty($data['form']['keterangan']) ? $data['form']['keterangan'] : '');
        
        
        
        if($data['form']['m_lokasi_asal_id'] != $data['form']['m_lokasi_tujuan_id']){
            $insert3_ = $insert3;
            $insert3_['m_akun_id'] = $getakun->m_akun_id;
            $insert3_['kredit'] = NULL;
            $insert3_['debit'] = $data['form']['total'];
            $transDetail[$index] = $insert3_;
            $transDetail[$index+1] = $insert3;
        }else{
            $transDetail[$index] = $insert3;
        }
        
        
        /*
         * Simpan array trans detail ke database jika simpan dan kunci
         */
        if($params['type_save'] == "kunci"){
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
    $db   = $this->db;

    
    $model = $db->delete("acc_transfer", ['id' => $data['id']]);
    $model = $db->deleted("acc_trans_detail", ["reff_type" => "acc_transfer", "reff_id" => $data['id']]);
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});
