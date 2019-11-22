<?php
/**
 * Validasi
 * @param  array $data
 * @param  array $custom
 * @return array
 */
function validasi($data, $custom = array())
{
    $validasi = array(
        "nama" => "required",
    );
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->get("/acc/appakses/tes", function ($request, $response) {
    $db = $this->db;
    $child = getChildId("acc_m_lokasi", 0);
    echo json_encode($child);

    // $db->run("update acc_m_akun set tipe='PENGELUARAN LAIN' where id in (".implode(",", $child).")");
});
/**
 * Ambil semua hak akses
 */
$app->get("/acc/appakses/index", function ($request, $response) {
    $params = $request->getParams();
    $db     = $this->db;
    $db->select("*")
        ->from("acc_m_roles")
            ->where("is_deleted", "=", 0);
    /**
     * Filter
     */
    if (isset($params["filter"])) {
        $filter = (array) json_decode($params["filter"]);
        foreach ($filter as $key => $val) {
            $db->where($key, "LIKE", $val);
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
    $models    = $db->findAll();
    $totalItem = $db->count();
    foreach ($models as $val) {
        $val->akses = json_decode($val->akses);
    }
    return successResponse($response, ["list" => $models, "totalItems" => $totalItem]);
});
/**
 * Save hak akses
 */
$app->post("/acc/appakses/save", function ($request, $response) {
    $data     = $request->getParams();
    $db       = $this->db;
    $validasi = validasi($data);
    if ($validasi === true) {
        try {
            $data["akses"] = json_encode($data["akses"]);
            
            if (isset($data["id"])) {
                $model = $db->update("acc_m_roles", $data, ["id" => $data["id"]]);
            } else {
                $model = $db->insert("acc_m_roles", $data);
            }
            return successResponse($response, $model);
        } catch (Exception $e) {
            return unprocessResponse($response, ["terjadi masalah pada server"]);
        }
    }
    return unprocessResponse($response, $validasi);
});
/**
 * Save status akses
 */
$app->post("/acc/appakses/saveStatus", function ($request, $response) {
    $data     = $request->getParams();
    $db       = $this->db;
    $validasi = validasi($data);
    if ($validasi === true) {
        try {
            $data["akses"] = json_encode($data["akses"]);
            $model         = $db->update("acc_m_roles", $data, ["id" => $data["id"]]);
            return successResponse($response, $model);
        } catch (Exception $e) {
            return unprocessResponse($response, ["terjadi masalah pada server"]);
        }
    }
    return unprocessResponse($response, $validasi);
});
