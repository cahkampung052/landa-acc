<?php

function validasi($data, $custom = array())
{
    $validasi = array(
        'kode'      => 'required',
        'nama'      => 'required',
    );
//    GUMP::set_field_name("parent_id", "Akun");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->get('/acc/m_lokasi/getLokasi', function ($request, $response) {
    $db = $this->db;
    $models = $db->select("*")
                ->from("acc_m_lokasi")
                ->orderBy('acc_m_lokasi.nama')
                ->where("is_deleted", "=", 0)
                ->findAll();
    return successResponse($response, [
      'list'        => $models
    ]);
});

$app->get('/acc/m_lokasi/list', function ($request, $response){
    $db = $this->db;
    $models = $db->select("acc_m_lokasi.*")
        ->from('acc_m_lokasi')
//        ->where('is_parent', '=', '1')
        ->where('is_deleted', '=', 0)
        ->orderBy('acc_m_lokasi.kode ASC')
        ->findAll();
    return successResponse($response, ['list' => $models]);
});

$app->get('/acc/m_lokasi/index', function ($request, $response) {
    $params = $request->getParams();
    // $sort     = "m_akun.kode ASC";
    $offset   = isset($params['offset']) ? $params['offset'] : 0;
    $limit    = isset($params['limit']) ? $params['limit'] : 20;

    $db = $this->db;
    $db->select("*")
        ->from("acc_m_lokasi")
        ->orderBy('acc_m_lokasi.nama')
        ->where("is_deleted", "=", 0);

    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);

        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("is_deleted", '=', $val);
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
//        $spasi                            = ($val->level == 1) ? '' : str_repeat("···", $val->level - 1);
//        $val->nama_lengkap        = $spasi . $val->kode . ' - ' . $val->nama;
    }
//     print_r($models);exit();
    
//      print_r($arr);exit();
    return successResponse($response, [
      'list'        => $models,
      'totalItems'  => $totalItem,
      'base_url'    => str_replace('api/', '', config('SITE_URL'))
    ]);
});



$app->post('/acc/m_lokasi/save', function ($request, $response) {

    $params = $request->getParams();
    
    $sql    = $this->db;

    $validasi = validasi($params);
    if ($validasi === true) {
        if($params['parent_id'] == 0){
//            $params['is_parent'] = 1;
            $params['level'] = 1;
        }else{
//            $params['is_parent'] = 0;
            $getlevel = $sql->select("*")->from("acc_m_lokasi")->where("id", "=", $params['parent_id'])->find();
//            die();
            $params['level'] = $getlevel->level + 1;
        }
//        print_r($params);die();
        if(isset($params['id']) && !empty($params['id'])){
            $model = $sql->update("acc_m_lokasi", $params, ["id" => $params['id']]);
        }else{
            $model = $sql->insert("acc_m_lokasi", $params);
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

$app->post('/acc/m_lokasi/update', function ($request, $response) {

    $data = $request->getParams();
    $db   = $this->db;

    $validasi = validasi($data);

    if ($validasi === true) {

        

        $model = $db->update("acc_m_lokasi", $data, array('id' => $data['id']));
        if ($model) {
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, ['Data Gagal Di Simpan']);
        }
    } else {
        return unprocessResponse($response, $validasi);
    }
});

$app->post('/acc/m_lokasi/trash', function ($request, $response) {

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

    $model = $db->update("acc_m_lokasi", $data, array('id' => $data['id']));
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});

$app->post('/acc/m_lokasi/delete', function ($request, $response) {
    $data = $request->getParams();
    $db   = $this->db;

//    $cek = $db->select("*")
//    ->from("acc_trans_detail")
//    ->where("m_akun_id", "=", $request->getAttribute('id'))
//    ->find();
//
//    if ($cek) {
//        return unprocessResponse($response, ['Data Akun Masih Di Gunakan Pada Transaksi']);
//    }
//
//    $cek_komponenGaji = $db->select('*')
//    ->from('m_komponen_gaji')
//    ->where('m_akun_id','=',$data['id'])
//    ->find();
//
//    if (!empty($cek_komponenGaji)) {
//       return unprocessResponse($response, ['Data Akun Masih Di Gunakan Pada Master Komponen Gaji']);
//    }
//
//    $cek_Gaji = $db->select('*')
//    ->from('t_penggajian')
//    ->where('m_akun_id','=',$data['id'])
//    ->find();
//
//    if (!empty($cek_Gaji)) {
//       return unprocessResponse($response, ['Data Akun Masih Di Gunakan Pada Transaksi Penggajian']);
//    }

    $delete = $db->delete('acc_m_lokasi', array('id' => $data['id']));
       if ($delete) {
           return successResponse($response, ['data berhasil dihapus']);
       } else {
           return unprocessResponse($response, ['data gagal dihapus']);
       }
});
