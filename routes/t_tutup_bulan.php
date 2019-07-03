<?php

function validasi($data, $custom = array()) {
    $validasi = array(
//        'no_transaksi' => 'required',
//        'm_lokasi_id' => 'required',
//        'm_akun_id' => 'required',
//        'tanggal' => 'required',
//        'total' => 'required',
//        'dibayar_kepada' => 'required'
    );
//    GUMP::set_field_name("parent_id", "Akun");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->get('/acc/t_tutup_bulan/index', function ($request, $response) {
    $params = $request->getParams();

    $sort = "id DESC";
    $offset = isset($params['offset']) ? $params['offset'] : 0;
    $limit = isset($params['limit']) ? $params['limit'] : 10;

    $db = $this->db;

    /** Select Gudang from database */
    $db->select("
      acc_tutup_buku.*,
      acc_m_user.nama as namaUser
      ")
            ->from("acc_tutup_buku")
            ->leftJoin("acc_m_user", "acc_m_user.id=acc_tutup_buku.created_by")
            ->where("acc_tutup_buku.jenis", "=", "bulan");
    /** Add filter */
    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);
        foreach ($filter as $key => $val) {
            if ($key == "tahun") {
                if ($val != "semua") {
                    $db->where($key, '=', $val);
                }
            } else {
                $db->where($key, 'LIKE', $val);
            }
        }
    }

    /** Set limit */
    if (!empty($limit)) {
        $db->limit($limit);
    }

    /** Set offset */
    if (!empty($offset)) {
        $db->offset($offset);
    }

    /** Set sorting */
    if (!empty($params['sort'])) {
        $db->sort($sort);
    }

    $models = $db->findAll();
    $totalItem = $db->count();
    // print_r($models);exit();
    $array = [];
    foreach ($models as $key => $val) {
        $akun_ikhtisar = $db->find("select * from acc_m_akun where id ={$val->akun_ikhtisar_id}");
        $akun_pemindaian = $db->find("select * from acc_m_akun where id ={$val->akun_pemindahan_modal_id}");
        $tgl = date('Y-m-d', strtotime($val->tahun . '-' . $val->bulan . '-01'));
        $array[$key] = (array) $val;
        $array[$key]['akun_ikhtisar'] = $akun_ikhtisar;
        $array[$key]['akun_pemindahan_modal'] = $akun_pemindaian;
        $array[$key]['hasil_rp'] = rp($val->hasil_lr);
        $array[$key]['bln_tahun'] = $tgl;
    }


    return successResponse($response, ['list' => $array, 'totalItems' => $totalItem]);
});


$app->get('/acc/t_tutup_bulan/tahun', function ($request, $response) {
    $db = $this->db;
    $list = $db->findAll("select * from acc_tutup_buku");
    $list_tahun = [];
    foreach ($list as $val) {
        $list_tahun[] = $val->tahun;
    }

    $tahun = range(date("Y") - 3, date("Y") + 1);

    $listtahun = array_unique(array_merge($list_tahun, $tahun));

    return successResponse($response, $tahun);
});


$app->get('/acc/t_tutup_bulan/getDetail', function ($request, $response) {
    $params = $request->getParams();
//    print_r($params['akun_ikhtisar_id']);die();

    $tipe = ["Pendapatan", "Biaya"];
    $db = $this->db;

    $models = [];
    $sumdebit = 0;
    $sumkredit = 0;
    foreach ($tipe as $tipe1 => $tipe2) {
        
        $models[$tipe1]['nama'] = $tipe2; 
        
        $getAkun = $db->select("*")
                ->from("acc_m_akun")
                ->where("tipe", "LIKE", $tipe2)
                ->where("is_tipe", "=", 0)
                ->orderBy("kode")
                ->findAll();
        $detail = [];
        foreach ($getAkun as $key1 => $val1) {
            $getTransDetail = $db->select("SUM(debit) as debit, SUM(kredit) as kredit")
                    ->from("acc_trans_detail")
                    ->where("m_akun_id", "=", $val1->id)
                    ->where("tanggal", "LIKE", $params['tahun'] . "-" . $params['bulan'])
                    ->find();

            if(($getTransDetail->debit != NULL && $getTransDetail->debit != 0) || ($getTransDetail->kredit != NULL && $getTransDetail->kredit != 0)){
                $detail[$key1]['m_akun_id'] = $val1->id;
                $detail[$key1]['nama'] = $val1->nama;
                $detail[$key1]['debit'] = $getTransDetail->debit;
                $detail[$key1]['kredit'] = $getTransDetail->kredit;

                $sumdebit += intval($detail[$key1]['debit']);
                $sumkredit += intval($detail[$key1]['kredit']);
            }
            
        }
        
        $models[$tipe1]['detail'] = $detail;
    }


//    echo json_encode($models);die();

    return successResponse($response, [
        'list' => $models,
        'total_debit' => $sumdebit,
        'total_kredit' => $sumkredit,
    ]);
});



$app->post('/acc/t_tutup_bulan/create', function ($request, $response) {

    $params = $request->getParams();
    $data = $params;
//    print_r($data);die();
    $tanggal = $data['form']['tahun'] . "-" . $data['form']['bulan'] . "-01";
    $tanggal = date("Y-m-t", strtotime($tanggal));
    $detail = [];
    foreach($data['detail'] as $detail1 => $detail2){
        foreach($detail2['detail'] as $detaill => $detailll){
            $detail[$detaill] = $detailll;
        }
        
    }
//    print_r($detail);die();
    $sql = $this->db;
    $validasi = validasi($data);
    if ($validasi === true) {
        $cekData = $sql->select("*")->from("acc_tutup_buku")
                ->where("jenis", "=", "bulan")
                ->where("tahun", "=", $data['form']['tahun'])
                ->where("bulan", "=", $data['form']['bulan'])
                ->count();
        if($cekData > 0){
            return unprocessResponse($response, 'Data Sudah Ada');
            die();
        }
        
        $data['form']['jenis'] = "bulan";
        $data['form']['akun_ikhtisar_id'] = $data['form']['akun_ikhtisar_id']['id'];
        $data['form']['akun_pemindahan_modal_id'] = $data['form']['akun_pemindahan_modal_id']['id'];
        
        $model = $sql->insert("acc_tutup_buku", $data['form']);
        
        //trans_detail ikhtisar 
        $transdet1['m_akun_id'] = $data['form']['akun_ikhtisar_id'];
        $transdet1['tanggal'] = $tanggal;
        $transdet1['kredit'] = $data['total_kredit'];
        $transdet1['reff_type'] = "Tutup Bulan";
        $transdet1['reff_id'] = $model->id;
        
        $model2 = $sql->insert("acc_trans_detail", $transdet1);
        
        //trans_detail pemindahan
        $transdet1['m_akun_id'] = $data['form']['akun_pemindahan_modal_id'];
        $transdet1['tanggal'] = $tanggal;
        $transdet1['debit'] = $data['total_debit'];
        $transdet1['reff_type'] = "Tutup Bulan";
        $transdet1['reff_id'] = $model->id;
        
        $model3 = $sql->insert("acc_trans_detail", $transdet1);
        
        if($model && $model2 && $model3){
            $sql->insert("acc_m_setting", ['tanggal'=>$tanggal]);
            foreach($detail as $key => $val){
                $det['acc_tutup_buku_id'] = $model->id;
                $det['m_akun_id'] = $val['m_akun_id'];
                $det['debit'] = $val['debit'];
                $det['kredit'] = $val['kredit'];
                
                $model4 = $sql->insert("acc_tutup_buku_det", $det);
            }
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, 'Data Gagal Di Simpan');
        }
            
        
        
    } else {
        return unprocessResponse($response, $validasi);
    }
});

$app->post('/acc/t_pengeluaran/update', function ($request, $response) {

    $data = $request->getParams();
    $sql = $this->db;
//    print_r($data);die();
    $validasi = validasi($data);

    if ($validasi === true) {

        //update acc_pemasukan
        $insert['no_urut'] = $data['form']['no_urut'];
        $insert['no_transaksi'] = $data['form']['no_transaksi'];
        $insert['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
        $insert['m_akun_id'] = $data['form']['m_akun_id']['id'];
        $insert['dibayar_kepada'] = $data['form']['dibayar_kepada'];
        $insert['tanggal'] = date("Y-m-d h:i:s", strtotime($data['form']['tanggal']));
        $insert['total'] = $data['form']['total'];
        $model = $sql->update("acc_pengeluaran", $insert, ["id" => $data['form']['id']]);

        //update acc_trans_detail dari acc_pemasukan
        $insert2['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
        $insert2['m_akun_id'] = $data['form']['m_akun_id']['id'];
        $insert2['tanggal'] = date("Y-m-d", strtotime($data['form']['tanggal']));
        $insert2['kredit'] = $data['form']['total'];
        $insert2['reff_type'] = "Pengeluaran Header";
        $insert2['reff_id'] = $model->id;
        $model2 = $sql->update("acc_trans_detail", $insert2, ["reff_id" => $model->id, "reff_type" => "Pengeluaran Header"]);

        //select acc_pemasukan_detail lalu hapus acc_pemasukan_detail
        $selectdetail = $sql->select("*")
                ->from("acc_pengeluaran_det")
                ->where("acc_pengeluaran_id", "=", $data['form']['id'])
                ->findAll();
        $array_id = [];
        $array_akun = [];
        foreach ($selectdetail as $key => $val) {
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
            foreach ($data['detail'] as $keys => $vals) {

                //insert ke acc_pemasukan_det
                $detail['m_akun_id'] = $vals['m_akun_id']['id'];
                $detail['debit'] = $vals['debit'];
                $detail['acc_pengeluaran_id'] = $model->id;
                $detail['keterangan'] = $vals['keterangan'];
                $modeldetail = $sql->insert("acc_pengeluaran_det", $detail);

                //insert ke acc_trans_detail
                $detail2['m_akun_id'] = $vals['m_akun_id']['id'];
                $detail2['m_lokasi_id'] = $data['form']['m_lokasi_id']['id'];
                $detail2['tanggal'] = date("Y-m-d", strtotime($data['form']['tanggal']));
                $detail2['debit'] = $vals['debit'];
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

$app->post('/acc/t_pengeluaran/trash', function ($request, $response) {

    $data = $request->getParams();
    $db = $this->db;

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

    $model = $db->update("t_tutup_buku", $data, array('id' => $data['id']));
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});
