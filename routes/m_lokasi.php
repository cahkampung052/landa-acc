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
                ->orderBy('acc_m_lokasi.kode_parent')
                ->where("is_deleted", "=", 0)
                ->findAll();
     $arr = getChildFlat($models, 0);
    foreach ($arr as $key => $val) {
        $spasi                            = ($val->level == 0) ? '' : str_repeat("---", $val->level);
        $val->nama_lengkap        = $spasi . $val->kode . ' - ' . $val->nama;
    }
    
    return successResponse($response, [
      'list'        => $arr
    ]);
});


$app->get('/acc/m_lokasi/index', function ($request, $response) {
    $params = $request->getParams();
   
    $db = $this->db;
    $db->select("acc_m_lokasi.*, induk.kode as kodeInduk, induk.nama as namaInduk, induk.kode_parent as kodeParent, induk.level as level_induk")
        ->from("acc_m_lokasi")
        ->join("left join", "acc_m_lokasi induk", "induk.id = acc_m_lokasi.parent_id")
        ->where("acc_m_lokasi.is_deleted", "=", 0);

    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);
        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("acc_m_lokasi.is_deleted", '=', $val);
            } else {
                $db->where($key, 'LIKE', $val);
            }
        }
    }

    $models    = $db->findAll();

    $arr = getChildFlat($models, 0);
    
    foreach ($arr as $key => $val) {
        $spasi                            = ($val->level == 0) ? '' : str_repeat("---", $val->level);
        $val->nama_lengkap        = $spasi . $val->kode . ' - ' . $val->nama;
        $val->parent_id = ["id"=>$val->parent_id, "nama"=>$val->namaInduk, "kode"=>$val->kodeInduk, "level" => $val->level_induk];
    
        /*
         * cek child
         */
        $val->child = count(getChildFlat($models, $val->id));
        
    }
    
    return successResponse($response, [
      'list'        => $models
    ]);
});



$app->post('/acc/m_lokasi/save', function ($request, $response) {
    $params = $request->getParams();
//    print_r($params);die();
    $sql    = $this->db;

    $validasi = validasi($params);
    if ($validasi === true) {
        $parent_id = $params['parent_id'];
        if(isset($params['parent_id']) && $params['parent_id']['id'] != 0){
            $params['level'] = $params['parent_id']['level'] + 1;
        } else {
            $params['level'] = 0;
        }
        
        if (isset($params['id']) && !empty($params['id'])) {
            $params['parent_id'] = $params['parent_id']['id'];
            $model = $sql->update("acc_m_lokasi", $params, ["id" => $params['id']]);
        }else{
            $params['parent_id'] = $params['parent_id']['id'];
            $model = $sql->insert("acc_m_lokasi", $params);
        }

        $models = $sql->update("acc_m_lokasi", $params, ["id"=> $model->id]);
            
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
    $model = false;
    if($data['id'] != 1){
        $model = $db->update("acc_m_lokasi", $data, array('id' => $data['id']));
    }
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});

$app->post('/acc/m_lokasi/delete', function ($request, $response) {
    $data = $request->getParams();
    $db   = $this->db;

    $model = false;
    if($data['id'] != 1){
        $model = $db->delete('acc_m_lokasi', array('id' => $data['id']));
    }
    
    if ($model) {
        return successResponse($response, ['data berhasil dihapus']);
    } else {
        return unprocessResponse($response, ['data gagal dihapus']);
    }
});
