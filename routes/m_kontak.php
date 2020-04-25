<?php
/**
 * Ambil customer
 */
$app->get('/acc/m_kontak/getKontak', function ($request, $response) {
    $db = $this->db;
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
