<?php
$app->get('/acc/l_hutang/laporan', function ($request, $response) {
    $params = $request->getParams();
    $sql = $this->db;
    /**
     * tanggal awal
     */
    $tanggal_awal = new DateTime($params['startDate']);
    $tanggal_awal->setTimezone(new DateTimeZone('Asia/Jakarta'));
    /**
     * tanggal akhir
     */
    $tanggal_akhir = new DateTime($params['endDate']);
    $tanggal_akhir->setTimezone(new DateTimeZone('Asia/Jakarta'));
    /**
     * Format Tanggal
     */
    $tanggal_start = $tanggal_awal->format("Y-m-d");
    $tanggal_end = $tanggal_akhir->format("Y-m-d");
    /**
     * Siapkan sub header laporan
     */
    $data['tanggal'] = date("d M Y", strtotime($tanggal_start)) . ' s/d ' . date("d M Y", strtotime($tanggal_end));
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = (isset($params['nama_lokasi']) && !empty($params['nama_lokasi'])) ? $params['nama_lokasi'] : "Semua";
    /*
     * Siapkan parameter lokasi
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
         */
        else {
            $lokasiId = $params['m_lokasi_id'];
        }
    }
    /**
     * Proses laporan
     */
    if (isset($params['m_akun_id']) && isset($params['m_lokasi_id'])) {
        /*
         * ambil saldo awal hutang
         */
        $sql->select('sum(acc_trans_detail.debit) as debit, sum(acc_trans_detail.kredit) as kredit')
            ->from('acc_trans_detail');
        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $sql->customWhere("acc_trans_detail.m_lokasi_id IN ($lokasiId)");
        }
        $sql->andWhere('acc_trans_detail.m_akun_id', '=', $params['m_akun_id'])
            ->andWhere('date(acc_trans_detail.tanggal)', '<', $tanggal_start);
        if(isset($params['m_kontak_id']) && !empty($params['m_kontak_id'])){
            $sql->andWhere('acc_trans_detail.m_kontak_id', '=', $params['m_kontak_id']);
        }
        $getsaldohutang = $sql->find();
        $data["saldoAwal"] = $getsaldohutang->debit - $getsaldohutang->kredit;
        /*
         * ambil data transdetail hutang
         */
        $sql->select('acc_trans_detail.*')
            ->from('acc_trans_detail');
        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $sql->customWhere("acc_trans_detail.m_lokasi_id IN ($lokasiId)");
        }
        $sql->where('acc_trans_detail.m_akun_id', '=', $params['m_akun_id'])
            ->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)
            ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);
        if(isset($params['m_kontak_id']) && !empty($params['m_kontak_id'])){
            $sql->andWhere('acc_trans_detail.m_kontak_id', '=', $params['m_kontak_id']);
        }
        $listTransDetail = $sql->findAll();
        $arr = [];
        $data["debitAkhir"] = 0;
        $data["kreditAkhir"] = 0;
        $saldoSekarang = $data["saldoAwal"];
        foreach ($listTransDetail as $key => $val) {
            $saldoSekarang += (round($val->debit, 2) - round($val->kredit, 2));
            $val->debit = round($val->debit, 2);
            $val->kredit = round($val->kredit, 2);
            $arr[$key] = (array) $val;
            $arr[$key]['saldo_sekarang'] = $saldoSekarang;
            $data["debitAkhir"] += round($val->debit, 2);
            $data["kreditAkhir"] += round($val->kredit, 2);
        }
        $data["saldoAkhir"] = $data["saldoAwal"] + $data["debitAkhir"] - $data["kreditAkhir"];
        if (isset($params['export']) && $params['export'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/hutang.html', [
                "data" => $data,
                "detail" => $arr,
                "css" => modulUrl().'/assets/css/style.css',
            ]);
            header("Content-type: application/vnd.ms-excel");
            header("Content-Disposition: attachment;Filename=laporan-buku-besar.xls");
            echo $content;
        } elseif (isset($params['print']) && $params['print'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/hutang.html', [
                "data" => $data,
                "detail" => $arr,
                "css" => modulUrl().'/assets/css/style.css',
            ]);
            echo $content;
            echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
        } else {
            return successResponse($response, ["data" => $data, "detail" => $arr]);
        }
    } else {
        return unprocessResponse($response, ["Silahkan pilih lokasi dan akun terlebih dahulu"]);
    }
});
