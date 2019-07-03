<?php

function validasi($data, $custom = array())
{
    $validasi = array(
        'tlp' => 'required',
        'email'      => 'required',
        'nama'      => 'required',
        'alamat' => 'required'
    );
    GUMP::set_field_name("tlp", "No Telepon");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->get('/acc/m_supplier/getSupplier', function ($request, $response) {
    $db = $this->db;
    $models = $db->select("*")
                ->from("acc_m_supplier")
                ->orderBy('acc_m_supplier.nama')
                ->where("is_deleted", "=", 0)
                ->findAll();
    return successResponse($response, [
      'list'        => $models
    ]);
});

$app->get('/acc/m_supplier/index', function ($request, $response) {
    $params = $request->getParams();
    // $sort     = "m_akun.kode ASC";
    $offset   = isset($params['offset']) ? $params['offset'] : 0;
    $limit    = isset($params['limit']) ? $params['limit'] : 20;

    $db = $this->db;
    $db->select("*")
        ->from("acc_m_supplier")
        ->orderBy('acc_m_supplier.nama');

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
//     print_r($models);exit();
    
//      print_r($arr);exit();
    return successResponse($response, [
      'list'        => $models,
      'totalItems'  => $totalItem,
      'base_url'    => str_replace('api/', '', config('SITE_URL'))
    ]);
});



$app->post('/acc/m_supplier/create', function ($request, $response) {

    $params = $request->getParams();
    $data   = $params;
    $sql    = $this->db;

    $validasi = validasi($data);
    if ($validasi === true) {
        $model = $sql->insert("acc_m_supplier", $params);
        if ($model) {
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, ['Data Gagal Di Simpan']);
        }
    } else {
        return unprocessResponse($response, $validasi);
    }
});

$app->post('/acc/m_supplier/update', function ($request, $response) {

    $data = $request->getParams();
    $db   = $this->db;

    $validasi = validasi($data);

    if ($validasi === true) {

        

        $model = $db->update("acc_m_supplier", $data, array('id' => $data['id']));
        if ($model) {
            return successResponse($response, $model);
        } else {
            return unprocessResponse($response, ['Data Gagal Di Simpan']);
        }
    } else {
        return unprocessResponse($response, $validasi);
    }
});

$app->post('/acc/m_supplier/trash', function ($request, $response) {

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

    $model = $db->update("acc_m_supplier", $data, array('id' => $data['id']));
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});

$app->post('/acc/m_supplier/delete', function ($request, $response) {
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

    $delete = $db->delete('acc_m_supplier', array('id' => $data['id']));
       if ($delete) {
           return successResponse($response, ['data berhasil dihapus']);
       } else {
           return unprocessResponse($response, ['data gagal dihapus']);
       }
});
