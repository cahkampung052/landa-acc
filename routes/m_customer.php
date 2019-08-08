<?php
function validasi($data, $custom = array())
{
    $validasi = array(
       'nama'      => 'required',
    );
    $cek = validate($data, $validasi, $custom);
    return $cek;
}
$app->get('/acc/m_customer/getKontak', function ($request, $response) {
    $db = $this->db;
    $models = $db->select("*")
                ->from("acc_m_kontak")
                ->orderBy("acc_m_kontak.nama")
                ->where("is_deleted", "=", 0)
                ->findAll();
    
    foreach($models as $key => $val){
        $val->type = ucfirst($val->type);
    }
    return successResponse($response, [
      'list' => $models
    ]);
});
$app->get('/acc/m_customer/getCustomer', function ($request, $response) {
    $db = $this->db;
    $models = $db->select("*")
                ->from("acc_m_kontak")
                ->orderBy("acc_m_kontak.nama")
                ->where("is_deleted", "=", 0)
                ->where("type", "=", "customer")
                ->findAll();
    return successResponse($response, [
      'list' => $models
    ]);
});
$app->get('/acc/m_customer/index', function ($request, $response) {
    $params = $request->getParams();
    $offset   = isset($params['offset']) ? $params['offset'] : 0;
    $limit    = isset($params['limit']) ? $params['limit'] : 10;
    $db = $this->db;
    $db->select("*")
        ->from("acc_m_kontak")
        ->where("type", "=", "customer")
        ->orderBy('acc_m_kontak.nama');
    
    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);
        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("is_deleted", '=', $val);
            } else {
                $db->where($key, 'like', $val);
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
    return successResponse($response, [
      'list'        => $models,
      'totalItems'  => $totalItem,
    ]);
});
$app->post('/acc/m_customer/save', function ($request, $response) {
    $params = $request->getParams();
    $sql    = $this->db;
    
    /*
     * generate kode
     */
    $kode = generateNoTransaksi("customer", 0);
    
    $params["nama"] = isset($params["nama"]) ? $params["nama"] : "";
    $validasi = validasi($params);
    if ($validasi === true) {
        $params['type'] = "customer";
        if (isset($params["id"])) {
            if(isset($params["kode"]) && !empty($params["kode"])){
                $params["kode"] = $params["kode"];
            }else{
                $params["kode"] = $kode;
            }
            $model = $sql->update("acc_m_kontak", $params, array('id' => $params['id']));
        } else {
            $params["kode"] = $kode;
            $model = $sql->insert("acc_m_kontak", $params);
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
$app->post('/acc/m_customer/trash', function ($request, $response) {
    $data = $request->getParams();
    $db   = $this->db;
    $model = $db->update("acc_m_kontak", $data, array('id' => $data['id']));
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});
$app->post('/acc/m_customer/delete', function ($request, $response) {
    $data = $request->getParams();
    $db   = $this->db;
    $delete = $db->delete('acc_m_kontak', array('id' => $data['id']));
    if ($delete) {
        return successResponse($response, ['data berhasil dihapus']);
    } else {
        return unprocessResponse($response, ['data gagal dihapus']);
    }
});
