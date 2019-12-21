<?php
function validasi($data, $custom = array())
{
    $validasi = array(
        'nama' => 'required',
        'kode' => 'required',
    );
    $cek = validate($data, $validasi, $custom);
    return $cek;
}
$app->get('/acc/m_customer_all/kode', function ($request, $response) {
    return generateNoTransaksi("customerAll", 0);
});
$app->get('/acc/m_customer_all/getKontak', function ($request, $response) {
    $db     = $this->db;
    $params = $request->getParams();
    $db->select("*")
        ->from("acc_m_kontak")
        ->orderBy("acc_m_kontak.nama")
        ->where("is_deleted", "=", 0);
    if (isset($params['nama']) && !empty($params['nama'])) {
        $db->customWhere("nama LIKE '%" . $params['nama'] . "%'", "AND");
    }
    $models = $db->findAll();
    foreach ($models as $key => $val) {
        $val->type = ucfirst($val->type);
    }
    return successResponse($response, [
        'list' => $models,
    ]);
});
$app->get('/acc/m_customer_all/getKaryawan', function ($request, $response) {
    $db     = $this->db;
    $params = $request->getParams();
    $models = $db->select("*")
        ->from("karyawan")
        ->where("is_deleted", "=", 0)
        ->findAll();
    return successResponse($response, [
        'list' => $models,
    ]);
});
$app->get('/acc/m_customer_all/getCustomer', function ($request, $response) {
    $db     = $this->db;
    $params = $request->getParams();
    $db->select("*")
        ->from("acc_m_kontak")
        // ->orderBy("acc_m_kontak.nama")
        ->where("is_deleted", "=", 0)
        ->andWhere("type", "=", "customer")
        ->andWhere("nama", "!=", "");
    if (isset($params['nama']) && !empty($params['nama'])) {
        $db->customWhere("nama LIKE '%" . $params['nama'] . "%' OR kode LIKE '%" . $params['nama'] . "%'", "AND");
    }
    $models = $db->limit(20)->findAll();
    return successResponse($response, [
        'list' => $models,
    ]);
});
$app->get('/acc/m_customer_all/index', function ($request, $response) {
    $params = $request->getParams();
    $offset = isset($params['offset']) ? $params['offset'] : 0;
    $limit  = isset($params['limit']) ? $params['limit'] : 10;
    $db     = $this->db;
    $db->select("*") 
        ->from("acc_m_kontak")
        ->where("jenis", "=", 'customer')
        ->orderBy('acc_m_kontak.nama');

    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);
        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("is_deleted", '=', $val);
            } elseif ($key == "jenis") {
                $db->where("jenis", '=', 'customer');
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
    $db->orderBy("acc_m_kontak.id DESC");
    $models    = $db->findAll();
    $totalItem = $db->count();
    return successResponse($response, [
        'list'       => $models,
        'totalItems' => $totalItem,
    ]);
});
 $app->post('/acc/m_customer_all/save', function ($request, $response) {
     $params = $request->getParams();
     $sql    = $this->db;
     /*
      * generate kode
      */
     $kode           = generateNoTransaksi("customerAll", 0);
     $params["nama"] = isset($params["nama"]) ? $params["nama"] : "";
     $params["jenis"]= 'customer';
     $validasi       = validasi($params);
     if ($validasi === true) {
         $params['type'] = "customer";
         if (isset($params["id"])) {
             if (isset($params["kode"]) && !empty($params["kode"])) {
                 $params["kode"] = $params["kode"];
             } else {
                 $params["kode"] = $kode;
             }
             $model = $sql->update("acc_m_kontak", $params, array('id' => $params['id']));
         } else {
             $params["kode"] = $kode;
             $model          = $sql->insert("acc_m_kontak", $params);
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
$app->post('/acc/m_customer_all/trash', function ($request, $response) {
    $data  = $request->getParams();
    $db    = $this->db;
    $model = $db->update("acc_m_kontak", $data, array('id' => $data['id']));
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});
$app->post('/acc/m_customer_all/delete', function ($request, $response) {
    $data   = $request->getParams();
    $db     = $this->db;
    $delete = $db->delete('acc_m_kontak', array('id' => $data['id']));
    if ($delete) {
        return successResponse($response, ['data berhasil dihapus']);
    } else {
        return unprocessResponse($response, ['data gagal dihapus']);
    }
});
