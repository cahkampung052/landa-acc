<?php

/**
 * Validasi
 * @param  array $data
 * @param  array $custom
 * @return array
 */
function validasi($data, $custom = array()) {
    $validasi = array(
        "m_lokasi_id" => "required",
    );
    $cek = validate($data, $validasi, $custom);
    return $cek;
}
/**
 * Ambil semua pengajuan yg sudah di approve
 */
$app->get("/acc/apppertanggungjawaban/index", function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    $db->select("acc_t_pengajuan.*, acc_m_lokasi.nama as namaLokasi, acc_m_lokasi.kode as kodeLokasi, acc_m_user.nama as namaUser")
            ->from("acc_t_pengajuan")
            ->join("JOIN", "acc_m_lokasi", "acc_m_lokasi.id = acc_t_pengajuan.m_lokasi_id")
            ->join("JOIN", "acc_m_user", "acc_m_user.id = acc_t_pengajuan.created_by")
            ->where("acc_t_pengajuan.status", "=", "approved");
    
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
    $models = $db->findAll();
    $totalItem = $db->count();
    
    foreach($models as $key => $val){
        $models[$key] = (array)$val;
        $models[$key]['m_lokasi_id'] = ["id"=>$val->m_lokasi_id, "nama"=>$val->namaLokasi, "kode"=>$val->kodeLokasi];
        $models[$key]['tanggal_formated'] = date("d-m-Y H:i", strtotime($val->tanggal));
        $models[$key]['created_formated'] = $val->namaUser;
        
        /*
        * ambil sisa approve dari acc_approval_pengajuan
        */
        $sisa = $db->select("*")->from("acc_approval_pengajuan")->where("t_pengajuan_id", "=", $val->id)->where("status", "!=", "approved")->count();
        $models[$key]['sisa_approval'] = $sisa;
    }
    
    return successResponse($response, ["list" => $models, "totalItems" => $totalItem]);
});