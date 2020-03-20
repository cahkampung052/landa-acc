<?php

$app->get('/acc/l_neraca_saldo/laporan', function ($request, $response) {

    $subDomain = str_replace('api/', '', site_url());
    $data['img'] = imgLaporan();


    $params = $request->getParams();
    $sql = $this->db;
    /*
     * tanggal awal
     */
    $tanggal_awal = new DateTime($params['startDate']);
    $tanggal_awal->setTimezone(new DateTimeZone('Asia/Jakarta'));
    /*
     * tanggal akhir
     */

    /*
     * lokasi
     */
    if (isset($params['m_lokasi_id']) && !empty($params["m_lokasi_id"])) {
        $lokasiId = getChildId("acc_m_lokasi", $params['m_lokasi_id']);
        if (!empty($lokasiId)) {
            array_push($lokasiId, $params['m_lokasi_id']);
            $lokasiId = implode(",", $lokasiId);
        } else {
            $lokasiId = $params['m_lokasi_id'];
        }
    }

//    print_r($lokasiId);die;

    $tanggal_akhir = new DateTime($params['endDate']);
    $tanggal_akhir->setTimezone(new DateTimeZone('Asia/Jakarta'));
    $tanggal_start = $tanggal_awal->format("Y-m-d");
    $tanggal_end = $tanggal_akhir->format("Y-m-d");
    $lokasi = isset($params['m_lokasi_id']) ? $params['m_lokasi_id'] : '';
    $data['tanggal'] = date("d M Y", strtotime($tanggal_start)) . ' s/d ' . date("d M Y", strtotime($tanggal_end));
    $data['lokasi'] = $params['nama_lokasi'];
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['debit_awal'] = 0;
    $data['kredit_awal'] = 0;
    $data['debit_mutasi'] = 0;
    $data['kredit_mutasi'] = 0;
    $data['debit_akhir'] = 0;
    $data['kredit_akhir'] = 0;
    /**
     * ambil saldo awal
     */
    $sql->select("SUM(debit) as debit, SUM(kredit) as kredit, acc_m_akun.id as m_akun_id, acc_m_akun.parent_id, acc_m_akun.is_tipe")
            ->from("acc_m_akun")
            ->leftJoin("acc_trans_detail", "acc_m_akun.id = acc_trans_detail.m_akun_id")
            ->andWhere('date(tanggal)', '<', $tanggal_start)
            ->groupBy("acc_m_akun.id")
            ->orderBy("acc_m_akun.is_tipe ASC, parent_id DESC, acc_m_akun.level DESC");
    if (isset($params['m_lokasi_id']) && !empty($params["m_lokasi_id"])) {
        $sql->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)", "AND");
    }

    $list = $sql->findAll();
    $arrSaldoAwal = [];
    foreach ($list as $key => $value) {
        $arrSaldoAwal[$value->m_akun_id]['debit'] = $value->debit;
        $arrSaldoAwal[$value->m_akun_id]['kredit'] = $value->kredit;

        $arrSaldoAwal[$value->parent_id]['debit'] = (isset($arrSaldoAwal[$value->parent_id]['debit']) ? $arrSaldoAwal[$value->parent_id]['debit'] : 0) + $arrSaldoAwal[$value->m_akun_id]['debit'];
        $arrSaldoAwal[$value->parent_id]['kredit'] = (isset($arrSaldoAwal[$value->parent_id]['kredit']) ? $arrSaldoAwal[$value->parent_id]['kredit'] : 0) + $arrSaldoAwal[$value->m_akun_id]['kredit'];
    }
    /**
     * Ambil mutasi dari trans detail
     */
    $sql->select("SUM(debit) as debit, SUM(kredit) as kredit, acc_m_akun.id as m_akun_id, acc_m_akun.parent_id")
            ->from("acc_m_akun")
            ->leftJoin("acc_trans_detail", "acc_m_akun.id = acc_trans_detail.m_akun_id")
            ->andWhere('date(tanggal)', '>=', $tanggal_start)
            ->andWhere('date(tanggal)', '<=', $tanggal_end)
            ->groupBy("acc_m_akun.id")
            ->orderBy("acc_m_akun.is_tipe ASC, parent_id DESC, acc_m_akun.level DESC");
    if (isset($params['m_lokasi_id']) && !empty($params["m_lokasi_id"])) {
        $sql->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)", "AND");
    }
    $list = $sql->findAll();
    $arrMutasi = [];
    foreach ($list as $key => $value) {
        $arrMutasi[$value->m_akun_id]['debit'] = $value->debit;
        $arrMutasi[$value->m_akun_id]['kredit'] = $value->kredit;

        $arrMutasi[$value->parent_id]['debit'] = (isset($arrMutasi[$value->parent_id]['debit']) ? $arrMutasi[$value->parent_id]['debit'] : 0) + $arrMutasi[$value->m_akun_id]['debit'];
        $arrMutasi[$value->parent_id]['kredit'] = (isset($arrMutasi[$value->parent_id]['kredit']) ? $arrMutasi[$value->parent_id]['kredit'] : 0) + $arrMutasi[$value->m_akun_id]['kredit'];
    }
    /*
     * ambil akun
     */
    $getakun = $sql->select("*")
            ->from("acc_m_akun")
            ->where("is_deleted", "=", 0)
            ->findAll();
    $listAkun = buildTreeAkun($getakun, 0);
    $arrModel = flatten($listAkun);
    $arr = [];
    foreach ($arrModel as $key => $val) {
        $spasi = ($val->level == 1) ? '' : str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $val->level - 1);
        $arr2 = [];
        $arr2['kode'] = $val->kode;
        $arr2['nama'] = $spasi . $val->kode . ' - ' . $val->nama;
        $arr2['is_tipe'] = $val->is_tipe;
        /**
         * Set saldo awal
         */
        $getsaldoawal = isset($arrSaldoAwal[$val->id]) ? $arrSaldoAwal[$val->id] : ['debit' => 0, 'kredit' => 0];
        $arr2['saldo_awal'] = intval($getsaldoawal['debit']) - intval($getsaldoawal['kredit']);
        $arr2['debit_awal'] = intval($getsaldoawal['debit']);
        $arr2['kredit_awal'] = intval($getsaldoawal['kredit']);
        $data['debit_awal'] += $arr2['debit_awal'];
        $data['kredit_awal'] += $arr2['kredit_awal'];
        /*
         * set mutasi
         */
        $detail = isset($arrMutasi[$val->id]) ? $arrMutasi[$val->id] : ['debit' => 0, 'kredit' => 0];
        $arr2['debit'] = intval($detail['debit']);
        $arr2['kredit'] = intval($detail['kredit']);
        $arr2['mutasi'] = $detail['debit'] - $detail['kredit'];

        if ($arr2['saldo_awal'] + $arr2['mutasi'] >= 0) {
            $arr2['debit_akhir'] = $arr2['saldo_awal'] + $arr2['mutasi'];
            $arr2['kredit_akhir'] = 0;
            $data['debit_akhir'] += $arr2['debit_akhir'];
        } else {
            $arr2['kredit_akhir'] = $arr2['saldo_awal'] + $arr2['mutasi'];
            $arr2['debit_akhir'] = 0;
            $data['kredit_akhir'] += ($arr2['kredit_akhir'] * -1);
        }
        /**
         * Tampilkan Akun yang ada saldonya saja
         */
        // if ($arr2['saldo_awal'] != 0 || $arr2['debit'] != 0 || $arr2['kredit'] != 0) {
        $arr[$key] = $arr2;
        $data['debit_mutasi'] += $arr2['debit'];
        $data['kredit_mutasi'] += $arr2['kredit'];
        // }
    }
    if (isset($params['export']) && $params['export'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/neracaSaldo.html', [
            "data" => $data,
            "detail" => $arr,
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;Filename=laporan-neraca-saldo.xls");
        echo $content;
    } elseif (isset($params['print']) && $params['print'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/neracaSaldo.html', [
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
