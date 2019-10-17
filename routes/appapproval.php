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
        "min" => "required",
        "max" => "required",
        "tipe" => "required"
    );
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

/**
 * Ambil semua m setting approval
 */
$app->get("/acc/appapproval/index", function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    
    $tableuser = tableUser();
    
    $db->select("*")
            ->from("acc_m_setting_approval");
    /**
     * Filter
     */
    if (isset($params["filter"])) {
        $filter = (array) json_decode($params["filter"]);
        foreach ($filter as $key => $val) {
            $db->where($key, "LIKE", $val);
        }
    }
    $models = $db->groupBy("tipe, min, max")->findAll();
    $totalItem = $db->count();
    
    foreach ($models as $key => $val) {
        $models[$key] = (array) $val;
        $db->select("acc_m_setting_approval.*, " . $tableuser . ".nama as namaUser")->from("acc_m_setting_approval")
                ->join("JOIN", $tableuser, $tableuser.".id = acc_m_setting_approval.acc_m_user_id")
                ->where("tipe", "=", $val->tipe)
                ->where("min", "=", $val->min)
                ->where("max", "=", $val->max);
        $countuser = $db->count();
        $getuser = $db->findAll();
        $arr_id = [];
        if(empty($getuser)){
            $models[$key]['detail'] = [];
        }else{
            foreach ($getuser as $keys => $vals) {
                $models[$key]['detail'][$keys] = (array) $vals;
                $arr_id[] = $vals->id;
                $models[$key]['detail'][$keys]['acc_m_user_id'] = ["id"=>$vals->acc_m_user_id, "nama"=>$vals->namaUser];
            }
        }
        $models[$key]['arr_id'] = implode(", ", $arr_id);
        $models[$key]['jumlah_approval'] = $countuser;
    }
    return successResponse($response, ["list" => $models, "totalItems" => $totalItem]);
});
/**
 * Save m setting approval
 */
$app->post("/acc/appapproval/save", function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    $validasi = validasi($data["data"]);
    if ($validasi === true) {
        try {
            if ($data['data']['status'] == "update" && isset($data["data"]["arr_id"]) && !empty($data["data"]["arr_id"])) {
                $db->run("DELETE FROM acc_m_setting_approval WHERE id IN(".$data["data"]["arr_id"].")");
            }
            /**
             * Simpan detail
             */
            if (isset($data["detail"]) && !empty($data["detail"])) {
                foreach ($data["detail"] as $key => $val) {
                    $detail["tipe"] = $data["data"]["tipe"];
                    $detail["min"] = $data["data"]["min"];
                    $detail["max"] = $data["data"]["max"];
                    $detail["acc_m_user_id"] = isset($val["acc_m_user_id"]['id']) ? $val["acc_m_user_id"]['id'] : '';
                    $detail["sebagai"] = isset($val["sebagai"]) ? $val["sebagai"] : '';
                    $detail["level"] = isset($val["level"]) ? $val["level"] : '';
                    $db->insert("acc_m_setting_approval", $detail);
                }
            }
            return successResponse($response, $detail);
        } catch (Exception $e) {
            return unprocessResponse($response, ["terjadi masalah pada server"]);
        }
    }
    return unprocessResponse($response, $validasi);
});
/**
 * Hapus m setting approval
 */
$app->post("/acc/appapproval/hapus", function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    try {
        $model = $db->delete("acc_m_setting_approval", ["min" => $data["min"], "max" => $data["max"]]);
        return successResponse($response, $model);
    } catch (Exception $e) {
        return unprocessResponse($response, ["terjadi masalah pada server"]);
    }
    return unprocessResponse($response, $validasi);
});
