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
            ->where("acc_tutup_buku.jenis", "=", "tahun");
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
        $tgl = date('Y-m-d', strtotime($val->tahun . '-' . $val->bulan . '-01'));
        $array[$key] = (array) $val;
        $array[$key]['created_at'] = date('Y-m-d', strtotime($val->created_at));
        $array[$key]['hasil_rp'] = rp($val->hasil_lr);
        $array[$key]['bln_tahun'] = $tgl;
    }


    return successResponse($response, ['list' => $array, 'totalItems' => $totalItem]);
});

$app->post('/acc/t_tutup_bulan/save', function ($request, $response) {

    $params = $request->getParams();
    $data = $params;
    
    $bulan = date("m", strtotime($data['form']['bulan']));
    $tahun = date("Y", strtotime($data['form']['bulan']));
    $bulandepan = date('Y-m-d', strtotime('first day of next month'));
    echo $bulandepan;die();
    
    $sql = $this->db;
    $validasi = validasi($data['form']);
    if ($validasi === true) {
        $cekData = $sql->select("*")->from("acc_tutup_buku")
                ->where("jenis", "=", "tahun")
                ->where("tahun", "=", $tahun)
                ->where("bulan", "=", $bulan)
                ->count();
        if($cekData > 0){
            return unprocessResponse($response, 'Data Sudah Ada');
            die();
        }
        
        $insert['jenis'] = "tahun";
        $insert['tahun'] = $tahun;
        $insert['bulan'] = $bulan;
        
        if(isset($data['form']['id']) && !empty($data['form']['id'])){
            $model = $sql->update("acc_tutup_buku", $insert, ["id"=>$data['form']['id']]);
        }else{
            $model = $sql->insert("acc_tutup_buku", $insert);
        }
        
        if($model){
            $models = $sql->update("acc_m_setting", ["tanggal"=>$bulandepan], ["id"=>1]);
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, 'Data Gagal Di Simpan');
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
