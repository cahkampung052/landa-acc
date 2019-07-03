<?php

function validasi($data, $custom = array())
{
    $validasi = array(
        'nama'   => 'required',
        'tahun'  => 'required',
        'persentase' => 'required',
    );
//    GUMP::set_field_name("parent_id", "Akun");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

function validasi_pelepasan($data, $custom = array())
{
    $validasi = array(
        'status' => 'required',
    );
    GUMP::set_field_name("status", "Jenis Pelepasan");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}


$app->get('/acc/m_umur_ekonomis/index', function ($request, $response) {
    $params = $request->getParams();
    // $sort     = "m_akun.kode ASC";
    $offset = isset($params['offset']) ? $params['offset'] : 0;
    $limit  = isset($params['limit']) ? $params['limit'] : 20;

    $db = $this->db;
    $db->select("acc_umur_ekonomis.*")
        ->from("acc_umur_ekonomis")
        ->orderBy('acc_umur_ekonomis.id DESC');

    if (isset($params['filter'])) {
        $filter = (array) json_decode($params['filter']);

        foreach ($filter as $key => $val) {
            if ($key == 'is_deleted') {
                $db->where("acc_umur_ekonomis.is_deleted", '=', $val);
            } else if ($key == 'nama') {
                $db->where("acc_umur_ekonomis.nama", 'LIKE', $val);
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

    $models    = $db->findAll();
    $totalItem = $db->count();
    foreach ($models as $key => $value) {
        $value->persentase = (Float) $value->persentase;
    }
    return successResponse($response, [
        'list'       => $models,
        'totalItems' => $totalItem,
        'base_url'   => str_replace('api/', '', config('SITE_URL')),
    ]);
});

$app->post('/acc/m_umur_ekonomis/save', function ($request, $response) {

    $params = $request->getParams();
    $data   = $params;
    $sql    = $this->db;

    $validasi = validasi($data);
    if ($validasi === true) {


        if (isset($data["id"])) {
            $model = $sql->update("acc_umur_ekonomis", $data, array('id' => $data['id']));
        } else {
            $model = $sql->insert("acc_umur_ekonomis", $data);
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



$app->post('/acc/m_umur_ekonomis/trash', function ($request, $response) {

    $data = $request->getParams();
    $db   = $this->db;

    $model = $db->update("acc_umur_ekonomis", $data, array('id' => $data['id']));
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});

$app->post('/acc/m_umur_ekonomis/delete', function ($request, $response) {
    $data = $request->getParams();
    $db   = $this->db;
    $delete = $db->delete('acc_umur_ekonomis', array('id' => $data['id']));
    if ($delete) {
        return successResponse($response, ['data berhasil dihapus']);
    } else {
        return unprocessResponse($response, ['data gagal dihapus']);
    }
});
