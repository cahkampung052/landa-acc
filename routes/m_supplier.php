<?php

function validasi($data, $custom = array()) {
    $validasi = array(
        'nama' => 'required',
    );
    GUMP::set_field_name("tlp", "No Telepon");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->get('/acc/m_supplier/kode', function ($request, $response) {
    $params = $request->getParams();
    return isset($params['project']) && !empty($params['project']) && $params['project'] == "afu" ? generateNoTransaksi("afu_supplier", 0) : generateNoTransaksi("supplier", 0);
});

$app->get('/acc/m_supplier/getSupplier', function ($request, $response) {
    $db = $this->db;
    $params = $request->getParams();
    $db->select("*")
            ->from("acc_m_kontak")
            ->orderBy("acc_m_kontak.nama")
            ->where("is_deleted", "=", 0)
            ->where("type", "=", "supplier");

    if (isset($params['nama']) && !empty($params['nama'])) {
        $db->customWhere("nama LIKE '%" . $params['nama'] . "%' OR kode LIKE '%" . $params['nama'] . "%'", "AND");
    }

    $models = $db->limit(20)->findAll();
    return successResponse($response, [
        'list' => $models
    ]);
});

$app->get('/acc/m_supplier/index', function ($request, $response) {
    $params = $request->getParams();
    // $sort     = "m_akun.kode ASC";
    $offset = isset($params['offset']) ? $params['offset'] : 0;
    $limit = isset($params['limit']) ? $params['limit'] : 10;

    $db = $this->db;
    $db->select("*")
            ->from("acc_m_kontak")
            ->orderBy('acc_m_kontak.type, acc_m_kontak.nama')
            ->customWhere("type IN ('supplier', 'angkutan', 'pelabuhan', 'dokumen', 'gudang', 'lain-lain')", "AND");

    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);

        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("is_deleted", '=', $val);
            } else {
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

    $models = $db->findAll();
    $totalItem = $db->count();
//     print_r($models);exit();
//      print_r($arr);exit();
    return successResponse($response, [
        'list' => $models,
        'totalItems' => $totalItem,
        'base_url' => str_replace('api/', '', config('SITE_URL'))
    ]);
});



$app->post('/acc/m_supplier/save', function ($request, $response) {

    $params = $request->getParams();
    $data = $params;
    $sql = $this->db;

    /*
     * generate kode
     */
//    $kode = generateNoTransaksi("supplier", 0);

    $validasi = validasi($data);
    if ($validasi === true) {
        $params['kode'] = isset($params['acc_m_akun_id']) && !empty($params['acc_m_akun_id']) ? $params['acc_m_akun_id']['kode'] : $params['kode'];
        $params['acc_m_akun_id'] = isset($params['acc_m_akun_id']) && !empty($params['acc_m_akun_id']) ? $params['acc_m_akun_id']['id'] : NULL;
        $params['type'] = isset($params['type']) && !empty($params['type']) ? $params['type'] : "supplier";
        if (isset($params["id"])) {
//            if(isset($params["kode"]) && !empty($params["kode"])){
//                $params["kode"] = $params["kode"];
//            }else{
//                $params["kode"] = $kode;
//            }
            $model = $sql->update("acc_m_kontak", $params, array('id' => $params['id']));
        } else {
//            $params["kode"] = $kode;
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

$app->post('/acc/m_supplier/trash', function ($request, $response) {

    $data = $request->getParams();
    $db = $this->db;


    $model = $db->update("acc_m_kontak", $data, array('id' => $data['id']));
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});

$app->post('/acc/m_supplier/delete', function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;

    $delete = $db->delete('acc_m_kontak', array('id' => $data['id']));
    if ($delete) {
        return successResponse($response, ['data berhasil dihapus']);
    } else {
        return unprocessResponse($response, ['data gagal dihapus']);
    }
});
