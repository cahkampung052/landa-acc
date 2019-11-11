<?php

$app->get('/acc/l_budgeting/laporan', function ($request, $response) {
    $params = $request->getParams();
//    print_r($params);
//    die();
    $db = $this->db;

    /**
     * Ambil data akun
     */
    $getakun = $db->select("acc_m_akun.*, klasifikasi.nama as klasifikasi")
            ->from("acc_m_akun")
            ->leftJoin("acc_m_akun as klasifikasi", "klasifikasi.id = acc_m_akun.parent_id")
            ->customWhere("acc_m_akun.id = '" . $params['m_akun_id'] . "'")
            ->andWhere("acc_m_akun.is_deleted", "=", 0)
            ->orderBy("acc_m_akun.kode ASC")
            ->find();

    /*
     * lokasi
     */
    if (isset($params['m_lokasi_id'])) {
        $lokasiId = getChildId("acc_m_lokasi", $params['m_lokasi_id']);
        /*
         * jika lokasi punya child
         */
        if (!empty($lokasiId)) {
            $lokasiId[] = $params['m_lokasi_id'];
            $lokasiId = implode(",", $lokasiId);
        }
        /*
         * jika lokasi tidak punya child
         */ else {
            $lokasiId = $params['m_lokasi_id'];
        }
    }

    /**
     * Ambil id akun turunan klasifikasi di parameter
     */
    $childId = getChildId("acc_m_akun", $getakun->id);
//    print_r($childId);
//    die();
    if (!empty($childId)) {
        $childId = implode(",", $childId);
    } else {
        $childId = $getakun->id;
    }



    /*
     * ambil semua akun tipe = 0
     */
    $listAkun = $db->select("acc_m_akun.*, induk.nama as nama_induk, induk.kode as kode_induk")
            ->from("acc_m_akun")
            ->leftJoin("acc_m_akun as induk", "induk.id = acc_m_akun.parent_id")
            ->orderBy('acc_m_akun.kode')
            ->customWhere("acc_m_akun.id in (" . $childId . ")")
            ->where("acc_m_akun.is_deleted", "=", 0)
            ->where("acc_m_akun.is_tipe", "=", 0)
            ->findAll();
    $list = [];
//    print_r($listAkun);die();
    foreach ($listAkun as $key => $value) {
        $listAkun[$key] = (array) $value;

        /*
         * perulangan sebanyak 12 (bulan)
         */
        for ($i = 1; $i <= 12; $i++) {

            /*
             * ambil budget
             */
            $db->select("*")->from("acc_budgeting")
                    ->where("m_akun_id", "=", $value->id)
                    ->where("tahun", "=", $params['tahun'])
                    ->where("bulan", "=", $i);
            if (!empty($lokasiId)) {
                $db->customWhere("acc_budgeting.m_lokasi_id = '".$lokasiId."'", "AND");
            }
            $getBudget = $db->find();
//            print_r($getBudget);

            /*
             * set nomer bulan
             */
            $bulan = $i;
            if ($i < 10) {
                $bulan = 0 . "" . $i;
            }

            /*
             * ambil transdetail
             */
            $db->select("SUM(debit-kredit) AS nominal")->from("acc_trans_detail");
            if (!empty($lokasiId)) {
                $db->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)", "AND");
            }
            $db->where("m_akun_id", "=", $value->id)
                    ->where("tanggal", "LIKE", "" . $params['tahun'] . "-" . $bulan . "");

            $getTransDetail = $db->find();

            /*
             * isi nominal target dari budget
             */
            if (!$getBudget) {
                $listAkun[$key]['detail'][$i]['target'] = 0;
            } else {
                $listAkun[$key]['detail'][$i]['target'] = $getBudget->budget;
            }

            /*
             * isi nominal realisasi dari transdetail
             */
            if (!$getTransDetail || $getTransDetail->nominal == NULL) {
                $listAkun[$key]['detail'][$i]['realisasi'] = 0;
            } else {
                $listAkun[$key]['detail'][$i]['realisasi'] = $getTransDetail->nominal;
            }

            $listAkun[$key]['detail'][$i]['bulan'] = date('F', mktime(0, 0, 0, $i, 10));
        }
    }

//    die;

    /*
     * perulangan bulan untuk header
     */
    $listBulan = [];
    for ($i = 1; $i <= 12; $i++) {
        $listBulan[$i]['bulan'] = date('F', mktime(0, 0, 0, $i, 10));
    }

    /*
     * perulangan setiap bulan 2 nominal
     */
    $listSetiapBulan = [];
    $a = 1;
    for ($i = 1; $i <= 12; $i++) {
        $listSetiapBulan[$a]['nama'] = 'Target';
        $listSetiapBulan[$a + 1]['nama'] = 'Realisasi';
        $a += 2;
    }

    /*
     * return
     */
    $data['bulan'] = $listBulan;
    $data['setiapbulan'] = $listSetiapBulan;
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = $params['nama_lokasi'];

    if (isset($params['export']) && $params['export'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/budgeting.html', [
            "data" => $data,
            "detail" => $listAkun,
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;Filename=laporan-budgeting.xls");
        echo $content;
    } else if (isset($params['print']) && $params['print'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/budgeting.html', [
            "data" => $data,
            "detail" => $listAkun,
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        echo $content;
//        echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
    } else {
        return successResponse($response, ['detail' => $listAkun, 'data' => $data]);
    }
});




