<?php

/**
 * Validasi
 * @param  array $data
 * @param  array $custom
 * @return array
 */
function validasi($data, $custom = array()) {
    $validasi = array(
        "nama" => "required",
    );
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

/**
 * Ambil semua m_group_agen
 */
$app->get("/acc/m_group_akun/index", function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    $db->select("acc_m_akun_group.*")
            ->from("acc_m_akun_group")
            ->orderBy("id DESC");
    /**
     * Filter
     */
    if (isset($params["filter"])) {
        $filter = (array) json_decode($params["filter"]);
        foreach ($filter as $key => $val) {
            if (!empty($val) || $val == 0) {
                $db->where($key, "LIKE", $val);
            }
        }
    }

    /**
     * Set limit dan offset
     */
    if (isset($params["limit"]) && !empty($params["limit"])) {
        $db->limit($params["limit"]);
    }
    if (isset($params["offset"]) && !empty($params["offset"])) {
        $db->offset($params["offset"]);
    }

    // Sorting
    if (isset($params["sort"]) && !empty($params["sort"])) {
        if ($params['order'] == 'false') {
            $order = "DESC";
        } else {
            $order = "ASC";
        }
        $db->orderBy($params["sort"] . " " . $order);
    } else {
        $db->orderBy("acc_m_akun_group.id DESC");
    }

    $models = $db->findAll();
    $totalItem = $db->count();
    return successResponse($response, ["list" => $models, "totalItems" => $totalItem]);
});

/**
 * Save m_group_agen
 */
$app->post("/acc/m_group_akun/save", function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    $validasi = validasi($data);

    if ($validasi === true) {
        try {
            if (isset($data["id"])) {
                $model = $db->update("acc_m_akun_group", $data, ["id" => $data["id"]]);
            } else {
                $model = $db->insert("acc_m_akun_group", $data);
            }
            return successResponse($response, $model);
        } catch (Exception $e) {
            return unprocessResponse($response, ["terjadi masalah pada server"]);
        }
    }
    return unprocessResponse($response, $validasi);
});

$app->post('/acc/m_group_akun/trash', function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    $update['is_deleted'] = $data['is_deleted'];

    $model = $db->update("acc_m_akun_group", $update, array('id' => $data['id']));
    if ($model) {
        return successResponse($response, $model);
    } else {
        return unprocessResponse($response, ['Gagal menghapus data']);
    }
});

$app->post("/acc/m_group_akun/hapus", function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    try {
        $model = $db->delete("acc_m_akun_group", ["id" => $data["id"]]);
        return successResponse($response, $model);
    } catch (Exception $e) {
        return unprocessResponse($response, ["terjadi masalah pada server"]);
    }
    return unprocessResponse($response, $validasi);
});
