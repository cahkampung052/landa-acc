<?php

$app->get('/acc/l_jurnal_umum/laporan', function ($request, $response) {

    $subDomain = str_replace('api/', '', site_url());
    $data['img'] = imgLaporan();

    $params = $request->getParams();
    $tanggal_start = $params['startDate'];
    $tanggal_end = $params['endDate'];
    $data['tanggal'] = date("d M Y", strtotime($tanggal_start)) . ' s/d ' . date("d M Y", strtotime($tanggal_end));
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
        } else {
            $lokasiId = $params['m_lokasi_id'];
        }
    }
    $arr = [];
    $data['total_debit'] = 0;
    $data['total_kredit'] = 0;
    $index = 0;
    /*
     * ambil trans detail dari akun
     */
    $sql = $this->db;
    $sql->select("acc_trans_detail.id, acc_trans_detail.kode, 
                acc_trans_detail.debit, 
                acc_trans_detail.kredit, 
                acc_trans_detail.keterangan, 
                acc_trans_detail.tanggal, 
                acc_m_akun.kode as kodeAkun, 
                acc_m_akun.nama as namaAkun,
                acc_m_lokasi.kode as kodeLokasi,
                acc_m_lokasi.nama as namaLokasi,
                lokasi_saldo.nama as namaLokasiSaldo
        ")->from("acc_trans_detail");
    if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
        $sql->customWhere("acc_trans_detail.m_lokasi_jurnal_id IN ($lokasiId)");
    }
    $sql->leftJoin("acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
            ->leftJoin("acc_m_lokasi", "acc_m_lokasi.id = acc_trans_detail.m_lokasi_jurnal_id")
            ->leftJoin("acc_m_lokasi as lokasi_saldo", "lokasi_saldo.id = acc_trans_detail.m_lokasi_id")
            ->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)
            ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end)
            ->orderBy("acc_trans_detail.tanggal ASC, acc_trans_detail.kode ASC, acc_trans_detail.id ASC");
    $gettransdetail = $sql->findAll();
    foreach ($gettransdetail as $keys => $vals) {
        if ($vals->debit == null) {
            $vals->debit = 0;
        }
        if ($vals->kredit == null) {
            $vals->kredit = 0;
        }

        if ($vals->debit == 0 && $vals->kredit == 0) {
            
        } else {
            $arr[$keys] = (array) $vals;
            $data['total_debit'] += $vals->debit;
            $data['total_kredit'] += $vals->kredit;
        }
    }
    /*
     * sorting array berdasarkan tanggal
     */
    $kode = "";
    $namaLokasi = "";
    foreach ($arr as $key => $val) {
        $arr[$key]['namaLokasi'] = $val['namaLokasiSaldo'];
        if ($val['kode'] == $kode && $val['kode'] != "") {
            $arr[$key]['kode'] = "";
            $arr[$key]['kodeLokasi'] = "";
            $arr[$key]['tanggal'] = "";
        } else {
            $kode = $val['kode'];
            $namaLokasi = $val['namaLokasi'];
        }
    }
    if (isset($params['export']) && $params['export'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/jurnalUmum.html', [
            "data" => $data,
            "detail" => $arr,
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;Filename=laporan-buku-besar.xls");
        echo $content;
    } elseif (isset($params['print']) && $params['print'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/jurnalUmum.html', [
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
