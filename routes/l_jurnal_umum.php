<?php

$app->get('/acc/l_jurnal_umum/getTransaksi', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    $model = $db->findAll("select distinct(reff_type) as reff_type from acc_trans_detail");
    $arr = [];
    foreach ($model as $key => $value) {
        $nama = str_replace("t_", "", $value->reff_type);
        $nama = str_replace("_id", "", $nama);
        $nama = str_replace("acc_", "", $nama);
        $nama = str_replace("inv_", "", $nama);
        $nama = str_replace("_", " ", $nama);
        $arr[] = [
            'id' => $value->reff_type,
            'nama' => $nama
        ];
    }
    return successResponse($response, $arr);
});
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

    if (empty($params['status']) || (!empty($params['status']) && $params['status'] == 'posting')) {
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
        if (isset($params['m_transaksi_id']) && !empty($params['m_transaksi_id']) && $params['m_transaksi_id'] !== 0) {
            $sql->customWhere('acc_trans_detail.reff_type = "' . $params['m_transaksi_id'] . '"', 'AND');
        }
        $sql->leftJoin("acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
                ->leftJoin("acc_m_lokasi", "acc_m_lokasi.id = acc_trans_detail.m_lokasi_jurnal_id")
                ->leftJoin("acc_m_lokasi as lokasi_saldo", "lokasi_saldo.id = acc_trans_detail.m_lokasi_id")
                ->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)
                ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end)
                ->orderBy("acc_trans_detail.tanggal ASC, acc_trans_detail.kode ASC, acc_trans_detail.id ASC");
        $gettransdetail = $sql->findAll();
        foreach ($gettransdetail as $keys => $vals) {

            $vals->status = 'posting';

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
    }

    if (empty($params['status']) || (!empty($params['status']) && $params['status'] == 'pending')) {
        $sql->select("acc_jurnal_det.*, "
                        . "acc_jurnal.no_transaksi as kode, acc_jurnal.tanggal, acc_jurnal.keterangan,"
                        . "acc_m_akun.kode as kodeAkun, acc_m_akun.nama as namaAkun, "
                        . "acc_m_lokasi.kode as kodeLokasi, acc_m_lokasi.nama as namaLokasi")
                ->from("acc_jurnal_det")
                ->leftJoin("acc_jurnal", "acc_jurnal.id = acc_jurnal_det.acc_jurnal_id")
                ->leftJoin("acc_m_akun", "acc_m_akun.id = acc_jurnal_det.m_akun_id")
                ->leftJoin("acc_m_lokasi", "acc_m_lokasi.id = acc_jurnal_det.m_lokasi_id");

        $sql->where('date(acc_jurnal.tanggal)', '>=', $tanggal_start);
        $sql->where('date(acc_jurnal.tanggal)', '<=', $tanggal_end);

        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $sql->customWhere("acc_jurnal_det.m_lokasi_id IN ($lokasiId)", "AND");
        }

        $sql->orderBy("acc_jurnal.tanggal ASC, acc_jurnal.no_transaksi ASC");

        $getPending = $sql->findAll();

//    pd($getPending);

        foreach ($getPending as $key => $value) {

            $value->status = 'pending';
            $value->namaLokasiSaldo = $value->namaLokasi;

            $value->debit = !empty($value->debit) ? $value->debit : 0;
            $value->kredit = !empty($value->kredit) ? $value->kredit : 0;
            if (!empty($value->debit) || !empty($value->kredit)) {
                $arr[] = (array) $value;
                $data['total_debit'] += $value->debit;
                $data['total_kredit'] += $value->kredit;
            }
        }
    }

//    print_die($arr);
//    usort($arr, function ($item1, $item2) {
//        if ($item1['tanggal'] == $item2['tanggal'])
//            return 0;
//        return $item1['tanggal'] < $item2['tanggal'] ? -1 : 1;
//    });

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
