<?php

$app->get('/acc/l_budgeting/laporan', function ($request, $response) {
    $data['img'] = imgLaporan();
    $params = $request->getParams();
    $db = $this->db;

//    print_die($params);

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
     * Jika akun tidak ditemukan, laporan = kosong
     */
    if (!isset($getakun->id)) {
        return successResponse($response, []);
    }

    /*
     * kelola tanggal
     */
    $tanggal_awal = date("Y-m", strtotime($params['startDate'])) . "-01";
    $tanggal_akhir = date("Y-m", strtotime($params['endDate'])) . "-01";
    $tanggal_akhir = date("Y-m-d", strtotime($tanggal_akhir . '+1 month'));

//    print_die($tanggal_awal);
//    print_die($tanggal_akhir);

    $tanggal_sekarang = $tanggal_awal;
    $arr_tanggal = [];
    $arr_bulan = [];
    $arr_tahun = [];
    do {
        $arr_tanggal[] = [
            'awal' => date("Y-m-d", strtotime($tanggal_sekarang)),
            'akhir' => date("Y-m-t", strtotime($tanggal_sekarang)),
            'nama' => date("F", strtotime($tanggal_sekarang)),
        ];

        $arr_bulan[] = date("m", strtotime($tanggal_sekarang)) < 10 ? '0' . date("m", strtotime($tanggal_sekarang)) : date("m", strtotime($tanggal_sekarang));
        $arr_tahun[] = date("Y", strtotime($tanggal_sekarang));

        $tanggal_sekarang = date("Y-m-d", strtotime($tanggal_sekarang . '+1 month'));
    } while ($tanggal_sekarang != $tanggal_akhir);

//    print_die($arr_tanggal);
//    print_die($arr_bulan);
//    print_die($arr_tahun);
    $arr_bulan = implode(", ", array_unique($arr_bulan));
    $arr_tahun = implode(", ", array_unique($arr_tahun));

    /**
     * Ambil id akun turunan klasifikasi di parameter
     */
    $childId = getChildId("acc_m_akun", $getakun->id);
    if (!empty($childId)) {
        $childId = implode(",", $childId);
    } else {
        $childId = $getakun->id;
    }
    /*
     * ambil semua akun tipe = 0
     */
    $db->select("acc_m_akun.*, induk.nama as nama_induk, induk.kode as kode_induk")
            ->from("acc_m_akun")
            ->leftJoin("acc_m_akun as induk", "induk.id = acc_m_akun.parent_id")
            ->orderBy('acc_m_akun.kode')
            ->customWhere("acc_m_akun.id in (" . $childId . ")")
            ->where("acc_m_akun.is_deleted", "=", 0)
            ->where("acc_m_akun.is_tipe", "=", 0);

    if (!empty($params['m_akun_group_id'])) {
        $db->where("acc_m_akun.m_akun_group_id", "=", $params['m_akun_group_id']);
    }

    $listAkun = $db->findAll();

//    print_die($listAkun);

    /*
     * ambil semua budgeting
     */
    $db->select("*")->from("acc_budgeting")
            ->customWhere("m_akun_id in (" . $childId . ")", "AND")
            ->customWhere("tahun in ($arr_tahun)", "AND")
            ->customWhere("bulan in ($arr_bulan)", "AND");
    if (!empty($lokasiId)) {
        $db->customWhere("acc_budgeting.m_lokasi_id = '" . $lokasiId . "'", "AND");
    }

    if (!empty($params['m_akun_group_id'])) {
        $db->where("acc_budgeting.m_akun_group_id", "=", $params['m_akun_group_id']);
    }

    $getBudget = $db->findAll();

//    print_die($getBudget);

    $arrBudget = [];
    foreach ($getBudget as $key => $value) {
        $value->bulan = $value->bulan < 10 ? '0' . $value->bulan : $value->bulan;
        $arrBudget[$value->m_akun_id]['detail'][$value->tahun . "-" . $value->bulan] = (array) $value;
    }

//    print_die($arrBudget);

    /*
     * ambil transdetail
     */
    $db->select("*, debit-kredit AS nominal")->from("acc_trans_detail");
    if (!empty($lokasiId)) {
        $db->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)", "AND");
    }
    $db->customWhere("m_akun_id IN($childId)", "AND")
            ->where("tanggal", ">=", $tanggal_awal)
            ->where("tanggal", ">=", date("Y-m-d", strtotime($tanggal_akhir . '-1 days')));
    $getTransDetail = $db->findAll();

//    print_die($getTransDetail);

    $arrTransDetail = [];
    foreach ($getTransDetail as $key => $value) {
        if (isset($arrTransDetail[$value->m_akun_id]['detail'][date('Y-m', strtotime($value->tanggal))])) {
            $arrTransDetail[$value->m_akun_id]['detail'][date('Y-m', strtotime($value->tanggal))]['nominal'] += $value->nominal;
        } else {
            $arrTransDetail[$value->m_akun_id]['detail'][date('Y-m', strtotime($value->tanggal))] = (array) $value;
        }
    }

//    print_die($arrTransDetail);

    $list = [];
    $arr = [];
    foreach ($listAkun as $key => $value) {
        $listAkun[$key] = (array) $value;

        $arr[$value->id] = (array) $value;
        foreach ($arr_tanggal as $k => $v) {
            $arr[$value->id]['detail'][date("Y-m", strtotime($v['awal']))]['budget'] = isset($arrBudget[$value->id]['detail'][date("Y-m", strtotime($v['awal']))]['budget']) ? $arrBudget[$value->id]['detail'][date("Y-m", strtotime($v['awal']))]['budget'] : 0;
            $arr[$value->id]['detail'][date("Y-m", strtotime($v['awal']))]['realisasi'] = isset($arrTransDetail[$value->id]['detail'][date("Y-m", strtotime($v['awal']))]['nominal']) ? $arrTransDetail[$value->id]['detail'][date("Y-m", strtotime($v['awal']))]['nominal'] : 0;
            if (!isset($arr[$value->id]['total'])) {
                $arr[$value->id]['total'] = $arr[$value->id]['detail'][date("Y-m", strtotime($v['awal']))]['budget'];
            } else {
                $arr[$value->id]['total'] += $arr[$value->id]['detail'][date("Y-m", strtotime($v['awal']))]['budget'];
            }
            $arr[$value->id]['total'] += $arr[$value->id]['detail'][date("Y-m", strtotime($v['awal']))]['realisasi'];
        }
    }

//    print_die($arr);

    foreach ($arr as $key => $value) {
        if ($value['total'] == 0) {
            unset($arr[$key]);
        }
    }

//    print_die($arr);

    /*
     * return
     */
    $data['bulan'] = $arr_tanggal;
    $data['jumlah_bulan'] = count($arr_tanggal);
    $data['tanggal'] = date("d M Y", strtotime($params['startDate'])) . ' s/d ' . date("d M Y", strtotime($params['endDate']));
//    $data['setiapbulan'] = $listSetiapBulan;
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = $params['nama_lokasi'];
//    $data['tanggal'] = $params['tahun'];
    if (isset($params['export']) && $params['export'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/budgeting.html', [
            "data" => $data,
            "detail" => $arr,
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;Filename=laporan-budgeting.xls");
        echo $content;
    } elseif (isset($params['print']) && $params['print'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/budgeting.html', [
            "data" => $data,
            "detail" => $arr,
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        echo $content;
        echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
    } else {
        return successResponse($response, ['detail' => $arr, 'data' => $data]);
    }
});
