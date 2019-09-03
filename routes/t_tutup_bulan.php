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
    $tableuser = tableUser();
    $sort = "id DESC";
    $offset = isset($params['offset']) ? $params['offset'] : 0;
    $limit = isset($params['limit']) ? $params['limit'] : 10;

    $db = $this->db;

    /** Select tutupbulan from database */
    $db->select("
      acc_tutup_buku.*,
      ".$tableuser.".nama as namaUser
      ")
            ->from("acc_tutup_buku")
            ->leftJoin($tableuser, $tableuser.".id = acc_tutup_buku.created_by")
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
       
        $array[$key] = (array) $val;
        $array[$key]['tanggal'] = date('Y-m-d', $val->created_at);
        $array[$key]['created_at'] = date('d-m-Y', $val->created_at);
        $array[$key]['bln_tahun'] = date('F', mktime(0, 0, 0, $val->bulan, 10)) .", ". $val->tahun;
    }


    return successResponse($response, ['list' => $array, 'totalItems' => $totalItem]);
});

$app->post('/acc/t_tutup_bulan/save', function ($request, $response) {

    $params = $request->getParams();
    $data = $params;
    
    $bulan = date("m", strtotime($data['form']['bulan']));
    $tahun = date("Y", strtotime($data['form']['bulan']));
    $bulandepan = date('Y-m-d', strtotime('first day of next month', strtotime($data['form']['bulan'])));
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
        
        $insert['jenis'] = "bulan";
        $insert['tahun'] = $tahun;
        $insert['bulan'] = $bulan;
        
        if(isset($data['form']['id']) && !empty($data['form']['id'])){
            $model = $sql->update("acc_tutup_buku", $insert, ["id"=>$data['form']['id']]);
        }else{
            $model = $sql->insert("acc_tutup_buku", $insert);
        }
        
        if($model){
            $models = $sql->update("acc_m_setting", ["tanggal"=>$bulandepan], ["id"=>1]);
            $models2 = $sql->update("acc_trans_detail", ["is_tutup_bulan"=>1], "tanggal < '$bulandepan'");
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, 'Data Gagal Di Simpan');
        }
            
        
        
    } else {
        return unprocessResponse($response, $validasi);
    }
});

$app->post('/acc/t_tutup_bulan/delete', function ($request, $response) {

    $data = $request->getParams();
    $db = $this->db;
//    print_r($data);die();
    $bulanini = date('Y-m-d', strtotime('first day of this month', strtotime($data['tanggal'])));
    $bulandepan = date('Y-m-d', strtotime('first day of next month', strtotime($data['tanggal'])));
    echo $bulanini;die();
    $model = $db->delete("acc_tutup_buku", array('id' => $data['id']));
    if ($model) {
        $models = $sql->update("acc_m_setting", ["tanggal"=>$bulanini], ["id"=>1]);
        $models2 = $sql->update("acc_trans_detail", ["is_tutup_bulan"=>0], "tanggal < '$bulandepan' AND tanggal >= '$bulanini'");
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});
