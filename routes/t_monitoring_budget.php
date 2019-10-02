<?php

function validasi($data, $custom = array()) {
    $validasi = array(
        'kode' => 'required',
        'nama' => 'required',
    );
//    GUMP::set_field_name("parent_id", "Akun");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->get('/acc/t_monitoring_budget/index', function ($request, $response) {
    $params = $request->getParams();

    $db = $this->db;

    if (isset($params['tahun'])) {
        $tahun = date("Y", strtotime($params['tahun']));
    }

    $arr = [];

    $lokasi = $db->select("*")->from("acc_m_lokasi")->where("is_deleted", "=", 0)->findAll();
    foreach ($lokasi as $key => $val) {
        $arr[$val->id] = (array) $val;
        $arr[$val->id]['budget'] = 0;
        $arr[$val->id]['used_budget'] = 0;
        $arr[$val->id]['used_budget2'] = 0;
    }
//    print_r($lokasi);die;

    $db->select("*")->from("acc_budgeting");
    if (isset($params['tahun'])) {
        $db->where("tahun", "=", $tahun)->findAll();
    }

    $budget = $db->findAll();

    foreach ($budget as $key => $val) {
        if (isset($arr[$val->m_lokasi_id]) && !empty($arr[$val->m_lokasi_id])) {
            $arr[$val->m_lokasi_id]['budget'] += intval($val->budget);
        }
    }

    $db->select("*")
            ->from("acc_t_pengajuan");
    if (isset($params['tahun'])) {
        $db->where("tanggal", ">=", $tahun . "-01-01")
                ->where("tanggal", "<=", $tahun . "-12-31");
    }
//            ->where("tipe", "=", "Budgeting")
    $usedbudget = $db->customWhere("status = 'approved' OR status = 'terbayar'", "AND")
            ->findAll();

    $data['total'] = 0;
    foreach ($usedbudget as $key => $val) {
        if ($val->tipe == "Budgeting") {
            $arr[$val->m_lokasi_id]['used_budget'] += intval($val->jumlah_perkiraan);
        } else if ($val->tipe == "Non Budgeting") {
            $arr[$val->m_lokasi_id]['used_budget2'] += intval($val->jumlah_perkiraan);
        }
        $data['total'] += intval($val->jumlah_perkiraan);
    }


    return successResponse($response, [
        'list' => $arr,
        'data' => $data
    ]);
});

