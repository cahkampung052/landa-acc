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
$app->get('/acc/t_monitoring_budget/getDetail', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    $lokasi = isset($params['lokasi_id']) ? $params['lokasi_id'] : 0;
    $tahun = isset($params['tahun']) ? date("Y", strtotime($params['tahun'])) : 0;
    $db->select("acc_m_akun.kode, acc_m_akun.nama, acc_budgeting.bulan, acc_budgeting.tahun, acc_budgeting.budget, acc_budgeting.m_akun_id")
        ->from("acc_budgeting")
        ->leftJoin("acc_m_akun", "acc_m_akun.id = acc_budgeting.m_akun_id")
        ->where("acc_budgeting.m_lokasi_id", "=", $lokasi)
        ->andWhere("acc_budgeting.tahun", "=", $tahun)
        ->andWhere("acc_budgeting.budget", ">", 0);
    $model = $db->findAll();
    $arr = [];
    $total = 0;
    foreach ($model as $key => $value) {
        $arr[$value->m_akun_id] = (array) $value;
        $arr[$value->m_akun_id]['budget'] = (isset($arr[$value->m_akun_id]['budget']) ? $arr[$value->m_akun_id]['budget'] : 0) + $value->budget;
    }
    return successResponse($response, [
        'list' => $arr,
        'total' => $total
    ]);
});
