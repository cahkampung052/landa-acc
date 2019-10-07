<?php
$app->get('/acc/l_arus_kas/laporan', function ($request, $response) {
    $params = $request->getParams();
    $sql = $this->db;
    //tanggal awal
    $tanggal_awal = new DateTime($params['startDate']);
    $tanggal_awal->setTimezone(new DateTimeZone('Asia/Jakarta'));
    //tanggal akhir
    $tanggal_akhir = new DateTime($params['endDate']);
    $tanggal_akhir->setTimezone(new DateTimeZone('Asia/Jakarta'));
    $tanggal_start = $tanggal_awal->format("Y-m-d");
    $tanggal_end = $tanggal_akhir->format("Y-m-d");
    $data['tanggal'] = date("d-m-Y", strtotime($tanggal_start)) . ' Sampai ' . date("d-m-Y", strtotime($tanggal_end));
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = $params['nama_lokasi'];
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
         */
        else {
            $lokasiId = $params['m_lokasi_id'];
        }
    }
    $data = [
        "total_saldo" => 0,
        "saldo_awal" => 0,
        "saldo_akhir" => 0,
    ];
    $data['tanggal'] = date("d-m-Y", strtotime($tanggal_start)) . ' Sampai ' . date("d-m-Y", strtotime($tanggal_end));
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = $params['nama_lokasi'];
    $arr["Aktivitas Operasi"] = [];
    $arr["Investasi"] = [];
    $arr["Pendanaan"] = [];
    /**
     * Ambil id akun kas
     */
    $kas = $sql->select("id")->from("acc_m_akun")->where("is_kas", "=", 1)->andWhere("is_deleted", "=", 0)->findAll();
    $arrKas = [];
    foreach ($kas as $key => $value) {
        $arrKas[$value->id] = $value->id;
    }
    /**
     * Ambil neraca sebelum periode
     */
    $neracaBefore = getSaldoNeraca(null, null, $tanggal_start);
    foreach ($neracaBefore as $key => $value) {
        if (in_array($key, $arrKas)) {
            $data["saldo_awal"] += $value;
        }
    }
    /**
     * Ambil neraca sampai periode
     */
    $neracaAfter = getSaldoNeraca(null, null, $tanggal_end);
    foreach ($neracaAfter as $key => $value) {
        if (in_array($key, $arrKas)) {
            $data["saldo_akhir_neraca"] += $value;
        }
    }
    /**
     * Ambil Semua Akun dengan tipe arus kas
     */
    $sql->select("acc_m_akun.*, induk.id as id_induk, induk.nama as nama_induk, induk.kode as kode_induk, acc_m_akun.saldo_normal, acc_m_akun.tipe")
        ->from("acc_m_akun")
        ->leftJoin("acc_m_akun as induk", "induk.id = acc_m_akun.parent_id")
        ->customWhere("induk.tipe_arus IN('Aktivitas Operasi', 'Investasi', 'Pendanaan')")
        ->where("acc_m_akun.is_tipe", "=", 0)
        ->where("acc_m_akun.is_deleted", "=", 0)
        ->findAll();
    $getakun = $sql->findAll();
    foreach ($getakun as $key => $value) {
        /**
         * Ambil saldo awal
         */
        $saldoAwal = isset($neracaBefore[$value->id]) ? $neracaBefore[$value->id] : 0;
        /**
         * Ambil saldo periode
         */
        $saldoPeriode   = isset($neracaAfter[$value->id]) ? $neracaAfter[$value->id] : 0;
        $saldo          = ($saldoPeriode) - ($saldoAwal);
        $arr[$value->tipe_arus][$value->parent_id]["nama"] = $value->nama_induk;
        $arr[$value->tipe_arus][$value->parent_id]["kode"] = $value->kode_induk;
        $arr[$value->tipe_arus][$value->parent_id]["id"] = $value->id;
        // if($saldo != 0){
            if($value->tipe == "HARTA"){
                $saldo = $saldo * -1;
            }
            $data['total_saldo'] += $saldo;
            $arr[$value->tipe_arus][$value->parent_id]["detail"][] = [
                "saldo" => $saldo,
                "nama" => $value->kode." - ".$value->nama,
            ];
        // }
    }
    /**
     * Hapus akun yang tidak ada transaksi
     */
    // foreach ($arr as $key => $value) {
    //     foreach ($value as $k => $v) {
    //         if(!isset($arr[$key][$k]['detail'])){
    //             unset($arr[$key][$k]);
    //         }
    //     }
    // }
    $data["saldo_akhir"] = $data["saldo_awal"] + $data["total_saldo"];
    if (isset($params['export']) && $params['export'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/arusKas.html', [
                "data" => $data,
                "detail" => $arr,
                "css" => modulUrl() . '/assets/css/style.css',
            ]);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;Filename=laporan-buku-besar.xls");
        echo $content;
    } elseif (isset($params['print']) && $params['print'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/arusKas.html', [
                "data" => $data,
                "detail" => $arr,
                "css" => modulUrl() . '/assets/css/style.css',
            ]);
        echo $content;
        echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
    } else {
        return successResponse($response, ["data" => $data, "detail" => $arr]);
    }
});
